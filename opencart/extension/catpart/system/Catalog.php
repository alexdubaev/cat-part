<?php
declare(strict_types=1);
namespace Catpart;

use PDO;
use InvalidArgumentException;

/** The existing CatPart tables are the single catalog; no OpenCart product mirror. */
final class Catalog
{
    private const TABLES=['product'=>'products','category'=>'categories','brand'=>'brands','attribute'=>'attributes'];
    public function __construct(private PDO $pdo) {}
    public static function migrate(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_catalog_meta(entity VARCHAR(30) NOT NULL,entity_id INT UNSIGNED NOT NULL,data LONGTEXT NOT NULL,PRIMARY KEY(entity,entity_id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_product_category(product_id INT UNSIGNED NOT NULL,category_id INT UNSIGNED NOT NULL,PRIMARY KEY(product_id,category_id),KEY(category_id)) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_product_relation(product_id INT UNSIGNED NOT NULL,related_id INT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,PRIMARY KEY(product_id,related_id,kind)) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_image_caption(image_id INT UNSIGNED PRIMARY KEY,caption VARCHAR(500) NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
        CatalogImport::migrate($pdo);
    }
    public static function metadata(PDO $pdo,string $entity,int $id):array
    {
        $q=$pdo->prepare('SELECT data FROM cp_catalog_meta WHERE entity=? AND entity_id=?');$q->execute([$entity,$id]);$v=$q->fetchColumn();return $v===false?[]:(json_decode((string)$v,true,512,JSON_THROW_ON_ERROR)?:[]);
    }
    public static function normalizeSku(string $sku):string
    {
        return mb_strtoupper(preg_replace('/[\s\-\._]+/u','',trim($sku))??'','UTF-8');
    }
    public function listing(string $type,string $query='',int $page=1):array
    {
        $table=$this->table($type);$where='';$args=[];
        if($query!==''){$where=$type==='product'?' WHERE (name LIKE ? OR sku_norm=?)':' WHERE name LIKE ?';$args=['%'.$query.'%'];if($type==='product')$args[]=self::normalizeSku($query);}
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM '.$table.$where);$q->execute($args);$count=(int)$q->fetchColumn();
        $q=$this->pdo->prepare('SELECT * FROM '.$table.$where.' ORDER BY id DESC LIMIT 40 OFFSET '.((max(1,$page)-1)*40));$q->execute($args);
        return ['rows'=>$q->fetchAll(),'total'=>$count,'pages'=>max(1,(int)ceil($count/40))];
    }
    public function choices(string $type):array
    {
        return $this->pdo->query('SELECT id,name FROM '.$this->table($type).' ORDER BY name,id')->fetchAll();
    }
    public function record(string $type,int $id):?array
    {
        $q=$this->pdo->prepare('SELECT * FROM '.$this->table($type).' WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)return null;
        $r=array_replace($r,self::metadata($this->pdo,$type,$id));$r['status']=$r['status']??(!empty($r['is_active'])?'published':'draft');
        if($type==='product'){
            $r['images']=$this->rows('SELECT i.*,c.caption FROM product_images i LEFT JOIN cp_image_caption c ON c.image_id=i.id WHERE i.product_id=? ORDER BY i.is_primary DESC,i.sort,i.id',[$id]);
            $r['cross']=$this->rows('SELECT * FROM product_cross WHERE product_id=? ORDER BY id',[$id]);
            $r['attributes']=$this->rows('SELECT v.*,a.name,a.unit FROM product_attr_values v JOIN attributes a ON a.id=v.attribute_id WHERE v.product_id=? ORDER BY a.sort,a.name',[$id]);
            $r['category_ids']=array_column($this->rows('SELECT category_id FROM cp_product_category WHERE product_id=?',[$id]),'category_id');
            $r['related_ids']=array_column($this->rows("SELECT related_id FROM cp_product_relation WHERE product_id=? AND kind='related'",[$id]),'related_id');
            $r['analog_ids']=array_column($this->rows("SELECT related_id FROM cp_product_relation WHERE product_id=? AND kind='analog'",[$id]),'related_id');
            $r['price_mode']=$r['price_mode']??(($r['price']??null)===null?'request':'fixed');
        }
        return $r;
    }
    public function save(string $type,array $input,int $actor,bool $confirmSlug=false):int
    {
        $this->table($type);$id=(int)($input['id']??0);$old=$id?$this->record($type,$id):null;
        if($id && !$old)throw new InvalidArgumentException('Запись не найдена.');
        $v=array_replace($old??[],$input);$name=CatalogSafety::text($v['name']??'', $type==='product'?255:($type==='attribute'?120:200),'Название',true);
        return $this->atomic(function()use($type,$id,$old,$input,$v,$name,$actor,$confirmSlug):int{
            if($type==='attribute')return $this->saveAttribute($id,$v,$name,$actor);
            $slug=CatalogSafety::text($v['slug']??'',180,'Адрес');if($slug==='')$slug=trim(preg_replace('/[^\p{L}\p{N}]+/u','-',mb_strtolower(($v['sku']??'').'-'.$name))??'','-');
            if(!preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_-]*$/uD',$slug) || in_array(mb_strtolower($slug),['admin','catalog','poisk','korzina','stati','robots','sitemap','api','index'],true))throw new InvalidArgumentException('Адрес: недопустимый или зарезервированный slug.');
            $q=$this->pdo->prepare('SELECT id FROM '.$this->table($type).' WHERE slug=? AND id<>?');$q->execute([$slug,$id]);if($q->fetchColumn())throw new InvalidArgumentException('Адрес уже занят другой записью.');
            if($old && $old['slug']!==$slug && !$confirmSlug)throw new InvalidArgumentException('Адрес изменён: подтвердите смену и создание перенаправления.');
            $status=(string)($v['status']??'draft');if(!in_array($status,['draft','published','archived'],true))throw new InvalidArgumentException('Публикация: неизвестное состояние.');
            $fields=['name'=>$name,'slug'=>$slug,'is_active'=>$status==='published'?1:0];
            foreach(['seo_h1'=>255,'seo_title'=>255,'seo_description'=>500] as $field=>$max)$fields[$field]=CatalogSafety::text($v[$field]??'',$max,$field);
            $meta=self::metadata($this->pdo,$type,$id);$meta['status']=$status;
            $meta['canonical']=CatalogSafety::url((string)($v['canonical']??''));$meta['noindex']=!empty($v['noindex']);$meta['og_image']=CatalogSafety::image((string)($v['og_image']??''));
            if($type==='product'){
                $fields['sku']=CatalogSafety::text($v['sku']??'',120,'Артикул',true);$fields['sku_norm']=self::normalizeSku($fields['sku']);
                $fields['category_id']=$this->reference('category',$v['category_id']??null);$fields['brand_id']=$this->reference('brand',$v['brand_id']??null);
                $priceMode=(string)($v['price_mode']??'request');if(!in_array($priceMode,['request','fixed'],true))throw new InvalidArgumentException('Цена: выберите режим.');
                $price=trim((string)($v['price']??''));if($priceMode==='fixed' && (!preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D',$price) || (float)str_replace(',','.',$price)<=0))throw new InvalidArgumentException('Цена: укажите положительное число, до двух знаков после запятой.');
                $fields['price']=$priceMode==='request'?null:str_replace(',','.',$price);$fields['currency']=strtoupper((string)($v['currency']??'RUB'));
                if(!preg_match('/^[A-Z]{3}$/D',$fields['currency']))throw new InvalidArgumentException('Валюта: требуется код из трёх букв.');
                $fields['in_stock']=!empty($v['in_stock'])?1:0;$fields['body_html']=CatalogSafety::html((string)($v['body_html']??''));$fields['image_url']=CatalogSafety::image((string)($v['image_url']??''));
                $fields['seo_keywords']=CatalogSafety::text($v['seo_keywords']??'',500,'SEO keywords');
                $meta['price_mode']=$priceMode;$meta['short_description']=CatalogSafety::text($v['short_description']??'',2000,'Краткое описание');$meta['delivery_terms']=CatalogSafety::text($v['delivery_terms']??'',2000,'Условия поставки');
            } else {
                $fields['intro_html']=CatalogSafety::html((string)($v['intro_html']??''));$fields['sort']=$this->integer($v['sort']??100,-100000,100000,'Порядок');
                if($type==='category'){
                    $parent=$this->reference('category',$v['parent_id']??null);if($parent===$id && $id)throw new InvalidArgumentException('Родитель: категория не может входить сама в себя.');
                    if($parent && $old){$p=$this->record('category',$parent);if(str_starts_with($p['path'],$old['path']))throw new InvalidArgumentException('Родитель: перемещение создаёт цикл категорий.');}
                    $fields['parent_id']=$parent;$fields['image_url']=CatalogSafety::image((string)($v['image_url']??''));$fields['seo_keywords']=CatalogSafety::text($v['seo_keywords']??'',500,'SEO keywords');
                }else{$fields['is_oem']=!empty($v['is_oem'])?1:0;$meta['logo_url']=CatalogSafety::image((string)($v['logo_url']??''));}
            }
            $newId=$this->write($this->table($type),$id,$fields);
            if($type==='category')$this->moveCategory($newId,$fields['parent_id'],$old);
            if($type==='product')$this->saveProductRelations($newId,$input,$v);
            $q=$this->pdo->prepare('INSERT INTO cp_catalog_meta(entity,entity_id,data) VALUES(?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data)');$q->execute([$type,$newId,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            if($old && $old['slug']!==$slug){$prefix=match($type){'product'=>'/tovar/','brand'=>'/brand/',default=>'/catalog/'};$this->redirectPath($prefix.$old['slug'],$prefix.$slug);}
            if($type==='product')$this->refreshCounts(array_merge([(int)($old['category_id']??0),(int)($fields['category_id']??0)],$old['category_ids']??[],$this->ids($v['category_ids']??[])));
            if($type==='category')$this->refreshCounts([(int)($old['parent_id']??0),(int)($fields['parent_id']??0),$newId]);
            Factory::audit($this->pdo,$actor,$type,(string)$newId,$id?'save':'create');return $newId;
        });
    }
    public function archive(string $type,int $id,string $confirmedName,int $actor):void
    {
        $old=$this->record($type,$id);if(!$old || !hash_equals($old['name'],$confirmedName))throw new InvalidArgumentException('Подтвердите название архивируемой записи.');
        if($type==='attribute')throw new InvalidArgumentException('Характеристика может быть связана с товарами. Удаление не поддерживается: исправьте определение или значения, связи сохраняются.');
        $this->save($type,['id'=>$id,'status'=>'archived'],$actor);
    }
    public function uploadImage(array $file,string $siteRoot):string
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??'')))throw new InvalidArgumentException('Изображение не загружено.');
        $path=$file['tmp_name'];if(filesize($path)>10*1024*1024)throw new InvalidArgumentException('Изображение: допустимо до 10 МиБ.');
        $info=@getimagesize($path);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;
        if(!$info || !$ext || $info[0]>12000 || $info[1]>12000 || $info[0]*$info[1]>40000000)throw new InvalidArgumentException('Нужны безопасные JPEG, PNG, WebP или GIF до 40 млн пикселей.');
        $dir=rtrim($siteRoot,'/\\').'/media/catalog';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new \RuntimeException('Не удалось создать папку изображений.');
        $name=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($path,$dir.'/'.$name))throw new \RuntimeException('Не удалось сохранить изображение.');return '/media/catalog/'.$name;
    }
    private function saveAttribute(int $id,array $v,string $name,int $actor):int
    {
        $code=CatalogSafety::text($v['code']??'',60,'Код характеристики',true);if(!preg_match('/^[A-Za-z0-9_-]+$/D',$code))throw new InvalidArgumentException('Код характеристики: латинские буквы, цифры, дефис.');
        $type=(string)($v['type']??'str');if(!in_array($type,['str','num','enum'],true))throw new InvalidArgumentException('Тип характеристики: неизвестное значение.');
        $q=$this->pdo->prepare('SELECT id FROM attributes WHERE code=? AND id<>?');$q->execute([$code,$id]);if($q->fetchColumn())throw new InvalidArgumentException('Код характеристики уже занят.');
        $id=$this->write('attributes',$id,['name'=>$name,'code'=>$code,'unit'=>CatalogSafety::text($v['unit']??'',40,'Единица'),'type'=>$type,'sort'=>$this->integer($v['sort']??100,-100000,100000,'Порядок'),'is_filterable'=>!empty($v['is_filterable'])?1:0]);Factory::audit($this->pdo,$actor,'attribute',(string)$id,'save');return $id;
    }
    private function saveProductRelations(int $id,array $input,array $v):void
    {
        if(array_key_exists('category_ids',$input)){
            $this->pdo->prepare('DELETE FROM cp_product_category WHERE product_id=?')->execute([$id]);foreach($this->ids($input['category_ids']) as $cat){$this->reference('category',$cat);$this->pdo->prepare('INSERT INTO cp_product_category VALUES(?,?)')->execute([$id,$cat]);}
        }
        foreach(['related_ids'=>'related','analog_ids'=>'analog'] as $key=>$kind)if(array_key_exists($key,$input)){
            $this->pdo->prepare('DELETE FROM cp_product_relation WHERE product_id=? AND kind=?')->execute([$id,$kind]);foreach($this->ids($input[$key]) as $other){if($other===$id)throw new InvalidArgumentException('Связанный товар не может ссылаться на себя.');$this->reference('product',$other);$this->pdo->prepare('INSERT INTO cp_product_relation VALUES(?,?,?)')->execute([$id,$other,$kind]);}
        }
        if(array_key_exists('cross',$input)){
            $this->pdo->prepare('DELETE FROM product_cross WHERE product_id=?')->execute([$id]);$seen=[];
            foreach((array)$input['cross'] as $r){$sku=CatalogSafety::text($r['cross_sku']??'',120,'Кросс-номер');if($sku==='')continue;$kind=$r['kind']??'analog';if(!in_array($kind,['oem','analog','replaced_by','replaces'],true))throw new InvalidArgumentException('Тип кросс-номера неизвестен.');$norm=self::normalizeSku($sku);if(isset($seen[$norm]))continue;$seen[$norm]=true;$this->pdo->prepare('INSERT INTO product_cross(product_id,cross_sku,cross_sku_norm,brand_name,kind) VALUES(?,?,?,?,?)')->execute([$id,$sku,$norm,CatalogSafety::text($r['brand_name']??'',120,'Бренд кросса'),$kind]);}
        }
        if(array_key_exists('attributes',$input)){
            $this->pdo->prepare('DELETE FROM product_attr_values WHERE product_id=?')->execute([$id]);$seen=[];
            foreach((array)$input['attributes'] as $r){$aid=(int)($r['attribute_id']??0);if(!$aid)continue;if(isset($seen[$aid]))throw new InvalidArgumentException('Характеристика указана дважды.');$seen[$aid]=true;$this->reference('attribute',$aid);$value=CatalogSafety::text($r['value_str']??'',255,'Значение характеристики');$this->pdo->prepare('INSERT INTO product_attr_values(product_id,attribute_id,value_str) VALUES(?,?,?)')->execute([$id,$aid,$value]);}
        }
        if(array_key_exists('images',$input)){
            $oldIds=array_column($this->rows('SELECT id FROM product_images WHERE product_id=?',[$id]),'id');
            if($oldIds)$this->pdo->exec('DELETE FROM cp_image_caption WHERE image_id IN('.implode(',',array_map('intval',$oldIds)).')');
            $this->pdo->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$id]);$rows=[];
            foreach((array)$input['images'] as $index=>$r){$url=CatalogSafety::image((string)($r['url']??''));if($url==='')continue;$rows[]=['url'=>$url,'alt'=>CatalogSafety::text($r['alt']??'',255,'Alt'),'caption'=>CatalogSafety::text($r['caption']??'',500,'Подпись'),'primary'=>(string)($input['primary_image']??'0')===(string)$index,'sort'=>count($rows)];}
            if($rows){$primary=array_search(true,array_column($rows,'primary'),true);if($primary===false)$primary=0;foreach($rows as $i=>$r){$this->pdo->prepare('INSERT INTO product_images(product_id,url,alt,is_primary,sort) VALUES(?,?,?,?,?)')->execute([$id,$r['url'],$r['alt'],$i===$primary?1:0,$r['sort']]);$imageId=(int)$this->pdo->lastInsertId();$this->pdo->prepare('INSERT INTO cp_image_caption VALUES(?,?)')->execute([$imageId,$r['caption']]);}$this->pdo->prepare('UPDATE products SET image_url=? WHERE id=?')->execute([$rows[$primary]['url'],$id]);}
            else{$this->pdo->prepare('UPDATE products SET image_url=? WHERE id=?')->execute([(string)($v['image_url']??''),$id]);}
        }elseif(array_key_exists('image_url',$input)){
            $url=(string)($v['image_url']??'');$this->pdo->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$id]);
            if($url!==''){$q=$this->pdo->prepare('SELECT id FROM product_images WHERE product_id=? AND url=? LIMIT 1');$q->execute([$id,$url]);$found=$q->fetchColumn();if($found)$this->pdo->prepare('UPDATE product_images SET is_primary=1 WHERE id=?')->execute([$found]);else $this->pdo->prepare('INSERT INTO product_images(product_id,url,alt,is_primary,sort) VALUES(?,?,?,1,0)')->execute([$id,$url,$v['name']??'']);}
        }
    }
    private function moveCategory(int $id,?int $parent,?array $old):void
    {
        $parentRow=$parent?$this->record('category',$parent):null;$path=($parentRow['path']??'/').$id.'/';$depth=$parentRow?(int)$parentRow['depth']+1:0;
        if(strlen($path)>255 || $depth>50)throw new InvalidArgumentException('Дерево категорий: слишком большая глубина.');
        if($old && $old['path']!==$path){$children=$this->rows('SELECT id,path,depth FROM categories WHERE path LIKE ? ORDER BY depth',[$old['path'].'%']);foreach($children as $child){$new=$path.substr($child['path'],strlen($old['path']));if(strlen($new)>255)throw new InvalidArgumentException('Дерево категорий: путь потомка слишком длинный.');$this->pdo->prepare('UPDATE categories SET path=?,depth=? WHERE id=?')->execute([$new,(int)$child['depth']+$depth-(int)$old['depth'],$child['id']]);}}
        $this->pdo->prepare('UPDATE categories SET path=?,depth=? WHERE id=?')->execute([$path,$depth,$id]);
    }
    private function refreshCounts(array $ids):void
    {
        $anc=[];foreach(array_unique(array_filter(array_map('intval',$ids))) as $id){$q=$this->pdo->prepare('SELECT path FROM categories WHERE id=?');$q->execute([$id]);foreach(explode('/',trim((string)$q->fetchColumn(),'/')) as $a)if(ctype_digit($a))$anc[(int)$a]=true;}
        foreach(array_keys($anc) as $id){$q=$this->pdo->prepare('UPDATE categories c SET products_count=(SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN categories pc ON pc.id=p.category_id WHERE p.is_active=1 AND ((pc.is_active=1 AND pc.path LIKE CONCAT(c.path,\'%\')) OR EXISTS(SELECT 1 FROM cp_product_category x JOIN categories xc ON xc.id=x.category_id WHERE x.product_id=p.id AND xc.is_active=1 AND xc.path LIKE CONCAT(c.path,\'%\')))) WHERE c.id=?');$q->execute([$id]);}
    }
    private function redirectPath(string $old,string $new):void
    {
        $this->pdo->prepare('DELETE FROM cp_redirect WHERE old_path=?')->execute([$new]);$this->pdo->prepare('UPDATE cp_redirect SET new_path=? WHERE new_path=?')->execute([$new,$old]);$this->pdo->prepare('INSERT INTO cp_redirect(old_path,new_path) VALUES(?,?) ON DUPLICATE KEY UPDATE new_path=VALUES(new_path)')->execute([$old,$new]);
    }
    private function reference(string $type,mixed $id):?int
    {
        if($id===null || $id==='' || $id==='0' || $id===0)return null;
        if(!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1)throw new InvalidArgumentException('Связь: выберите существующую запись.');
        $q=$this->pdo->prepare('SELECT id FROM '.$this->table($type).' WHERE id=?');$q->execute([(int)$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Связанная запись не найдена: '.$type.'.');return (int)$id;
    }
    private function ids(mixed $values):array
    {
        if(is_string($values))$values=preg_split('/[,;\s]+/',trim($values),-1,PREG_SPLIT_NO_EMPTY);
        if(!is_array($values))throw new InvalidArgumentException('Связи: требуется список выбранных записей.');
        $out=[];foreach($values as $v){if($v==='')continue;if(!is_scalar($v)||!ctype_digit((string)$v)||(int)$v<1)throw new InvalidArgumentException('Связи: неверный идентификатор.');$out[(int)$v]=true;}return array_keys($out);
    }
    private function integer(mixed $v,int $min,int $max,string $label):int
    {
        if(!is_scalar($v) || !preg_match('/^-?\d+$/D',(string)$v) || (int)$v<$min || (int)$v>$max)throw new InvalidArgumentException($label.': требуется целое число.');return (int)$v;
    }
    private function table(string $type):string{return self::TABLES[$type]??throw new InvalidArgumentException('Неизвестный раздел каталога.');}
    private function rows(string $sql,array $args=[]):array{$q=$this->pdo->prepare($sql);$q->execute($args);return $q->fetchAll();}
    private function write(string $table,int $id,array $fields):int
    {
        if($id){$sets=implode(',',array_map(static fn($k)=>'`'.$k.'`=?',array_keys($fields)));$this->pdo->prepare('UPDATE '.$table.' SET '.$sets.' WHERE id=?')->execute([...array_values($fields),$id]);return $id;}
        $this->pdo->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')')->execute(array_values($fields));return (int)$this->pdo->lastInsertId();
    }
    private function atomic(callable $run):mixed
    {
        $own=!$this->pdo->inTransaction();$savepoint='cp_catalog_save';if($own)$this->pdo->beginTransaction();else $this->pdo->exec('SAVEPOINT '.$savepoint);
        try{$result=$run();if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);return $result;}
        catch(\Throwable $e){if($this->pdo->inTransaction()){if($own)$this->pdo->rollBack();else{$this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);$this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);}}throw $e;}
    }
}
