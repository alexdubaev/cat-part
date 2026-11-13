<?php
declare(strict_types=1);
namespace Catpart;
use PDO;
use InvalidArgumentException;
use XMLReader;
use ZipArchive;

/** Server-side, resumable CSV/XLSX import. Decoding never trusts browser-submitted cells. */
final class CatalogImport
{
    public const MAX_BYTES=16777216;
    public const MAX_ROWS=100000;
    public const BATCH=250;
    public const STEP_SECONDS=10;
    public const FIELDS=['id'=>'ID товара','sku'=>'Артикул (текст)','name'=>'Название','category'=>'Основная категория: ID или точное имя','brand'=>'Бренд: ID или точное имя','price'=>'Цена','price_mode'=>'Цена: fixed / request','currency'=>'Валюта','in_stock'=>'Наличие: 1 / 0','delivery_terms'=>'Условия поставки','short_description'=>'Краткое описание','body_html'=>'Описание','image_url'=>'Основное изображение','cross_sku'=>'OEM / кросс-номера через ;','slug'=>'Адрес slug','seo_h1'=>'H1','seo_title'=>'Title','seo_description'=>'Description','status'=>'Статус: draft / published / archived'];
    public function __construct(private PDO $pdo,private ?string $privateRoot=null) {}
    public static function uploadLimit():int
    {
        $bytes=static function(string $v):int{$v=trim($v);if($v==='')return PHP_INT_MAX;$n=(int)$v;return $n*match(strtolower(substr($v,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};};
        $upload=$bytes((string)ini_get('upload_max_filesize'));$post=$bytes((string)ini_get('post_max_size'));
        return min(self::MAX_BYTES,$upload>0?$upload:PHP_INT_MAX,$post>0?max(0,$post-65536):PHP_INT_MAX);
    }
    public static function migrate(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_import(id CHAR(32) PRIMARY KEY,actor_id INT UNSIGNED NOT NULL,filename VARCHAR(255) NOT NULL,file_path VARCHAR(1000) NOT NULL,sha256 CHAR(64) NOT NULL,headers LONGTEXT NOT NULL,mapping LONGTEXT NOT NULL,status VARCHAR(30) NOT NULL,last_row INT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,KEY(actor_id,created_at)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_import_row(import_id CHAR(32) NOT NULL,line_number INT NOT NULL,identity_key VARCHAR(140) NOT NULL,input_data LONGTEXT NOT NULL,target_id INT UNSIGNED NULL,action VARCHAR(20) NOT NULL,before_hash CHAR(64) NULL,status VARCHAR(20) NOT NULL,message VARCHAR(1000) NOT NULL,PRIMARY KEY(import_id,line_number),KEY(import_id,identity_key),KEY(import_id,status,line_number)) ENGINE=InnoDB CHARACTER SET utf8mb4");
    }
    public function upload(array $file,int $actor):array
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??'')))throw new InvalidArgumentException('Файл не загружен. Проверьте лимит сервера.');
        return $this->stageFile($file['tmp_name'],(string)($file['name']??''),$actor);
    }
    public function stageFile(string $path,string $filename,int $actor):array
    {
        if(!is_file($path) || filesize($path)<1 || filesize($path)>self::MAX_BYTES)throw new InvalidArgumentException('Файл: допустимо от 1 байта до 16 МиБ.');
        $filename=CatalogSafety::text(basename(str_replace('\\','/',$filename)),255,'Имя файла',true);$ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
        if(!in_array($ext,['csv','xlsx'],true))throw new InvalidArgumentException('Допустимы только CSV и XLSX.');
        if($ext==='xlsx')$this->validateBook($path);
        else { $sample=file_get_contents($path,false,null,0,65536);if(str_contains($sample,"\0") || str_starts_with($sample,'PK'))throw new InvalidArgumentException('CSV содержит двоичные данные.'); }
        $root=$this->root();$id=bin2hex(random_bytes(16));$destination=$root.'/'.$id.'.'.$ext;
        if(!copy($path,$destination))throw new \RuntimeException('Не удалось сохранить приватный файл импорта.');@chmod($destination,0600);
        try{$headers=[];foreach($this->rows($destination) as $number=>$row){$headers=array_map(static fn($v)=>trim((string)$v),$row);break;}if(!$headers || count($headers)>200 || !array_filter($headers,static fn($v)=>$v!==''))throw new InvalidArgumentException('Первая строка должна содержать заголовки, не более 200 колонок.');
            $q=$this->pdo->prepare('INSERT INTO cp_import(id,actor_id,filename,file_path,sha256,headers,mapping,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,\'uploaded\',UTC_TIMESTAMP(),UTC_TIMESTAMP())');$q->execute([$id,$actor,$filename,$destination,hash_file('sha256',$destination),json_encode($headers,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'{}']);Factory::audit($this->pdo,$actor,'import',$id,'upload');return $this->job($id,$actor);
        }catch(\Throwable $e){@unlink($destination);throw $e;}
    }
    public function job(string $id,int $actor):array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Импорт не найден.');
        $q=$this->pdo->prepare('SELECT * FROM cp_import WHERE id=? AND actor_id=?');$q->execute([$id,$actor]);$r=$q->fetch();if(!$r)throw new InvalidArgumentException('Импорт не найден или недоступен.');
        $r['headers']=json_decode($r['headers'],true,512,JSON_THROW_ON_ERROR);$r['mapping']=json_decode($r['mapping'],true,512,JSON_THROW_ON_ERROR);
        $r['summary']=$this->summary($id);return $r;
    }
    public function preview(string $id,array $mapping,int $actor,bool $resume=false):array
    {
        $job=$this->job($id,$actor);$this->verifyFile($job);
        if(!$resume){
            if(in_array($job['status'],['applying','done'],true))throw new InvalidArgumentException('Уже применённый импорт нельзя изменить. Загрузите новый файл.');
            $fields=[];foreach($mapping as $column=>$field){if($field==='')continue;if(!ctype_digit((string)$column)||!array_key_exists((int)$column,$job['headers'])||!isset(self::FIELDS[$field]))throw new InvalidArgumentException('Сопоставление колонок: неизвестное поле.');if(isset($fields[$field]))throw new InvalidArgumentException('Одно поле не может быть сопоставлено двум колонкам.');$fields[$field]=true;}
            if(!isset($fields['sku'])&&!isset($fields['id']))throw new InvalidArgumentException('Сопоставьте артикул или ID товара.');
            $this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM cp_import_row WHERE import_id=?')->execute([$id]);$this->pdo->prepare("UPDATE cp_import SET mapping=?,status='previewing',last_row=1,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([json_encode($mapping,JSON_THROW_ON_ERROR),$id]);$this->pdo->commit();}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
            $job=$this->job($id,$actor);
        }elseif($job['status']!=='previewing')throw new InvalidArgumentException('Проверка не ожидает продолжения.');
        $catalog=new Catalog($this->pdo);$last_row=(int)$job['last_row'];$processed=0;$finished=true;$started=microtime(true);
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->prepare('SELECT last_row,status FROM cp_import WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetch();if($current['status']!=='previewing'||(int)$current['last_row']!==$last_row){$this->pdo->rollBack();return $this->job($id,$actor);}
            foreach($this->rows($job['file_path']) as $number=>$cells){
                if($number<=1 || $number<=$last_row)continue;
                if($number>self::MAX_ROWS+1)throw new InvalidArgumentException('Строка '.$number.': превышен лимит 100 000 строк. Разделите файл; ничего не обрезается.');
                $input=[];$target=null;$action='error';$message='';$notice='';$identity='';$hash=null;
                try{
                    foreach($job['mapping'] as $column=>$field)if($field!=='')$input[$field]=(string)($cells[(int)$column]??'');
                    if(!array_filter($input,static fn($v)=>trim($v)!=='')){$last_row=$number;continue;}
                    $input=$this->convert($input);$target=$this->target($input);$identity=isset($input['id'])&&trim((string)$input['id'])!==''?'id:'.$input['id']:'sku:'.Catalog::normalizeSku($input['sku']??'');
                    $q=$this->pdo->prepare('SELECT line_number FROM cp_import_row WHERE import_id=? AND identity_key=? AND status<>\'error\' LIMIT 1');$q->execute([$id,$identity]);$previous=$q->fetchColumn();if($previous)throw new InvalidArgumentException('Тот же товар уже указан в строке '.$previous.'.');
                    $existing=$target?$catalog->record('product',$target):null;
                    $this->validateRow(array_replace($existing??[],$input),$existing!==null);
                    if($existing && isset($input['slug']) && $input['slug']!==$existing['slug'])$notice='Смена адреса: /tovar/'.$existing['slug'].' → /tovar/'.$input['slug'].'; будет создан прямой 301.';
                    $action=$target?'update':'create';$hash=$existing?hash('sha256',json_encode($existing,JSON_THROW_ON_ERROR)):null;
                }catch(InvalidArgumentException $e){$message=$e->getMessage();}
                $this->pdo->prepare('INSERT INTO cp_import_row(import_id,line_number,identity_key,input_data,target_id,action,before_hash,status,message) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$id,$number,$identity,json_encode($input,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$target,$action,$hash,$message===''?'ready':'error',mb_substr($message!==''?$message:$notice,0,1000)]);
                $last_row=$number;$processed++;
                if($processed>=self::BATCH || microtime(true)-$started>=self::STEP_SECONDS){$finished=false;break;}
            }
            $this->pdo->prepare('UPDATE cp_import SET last_row=?,status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$last_row,$finished?'previewed':'previewing',$id]);$this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
        return $this->job($id,$actor);
    }
    public function apply(string $id,int $actor,bool $confirmed):array
    {
        if(!$confirmed)throw new InvalidArgumentException('Подтвердите применение проверенных изменений.');
        $job=$this->job($id,$actor);$this->verifyFile($job);
        if($job['status']==='done')return $job;
        if(!in_array($job['status'],['previewed','applying'],true))throw new InvalidArgumentException('Сначала завершите предварительную проверку.');
        if(($job['summary']['error']??0)>0 && $job['status']==='previewed')throw new InvalidArgumentException('Исправьте ошибки в файле до применения. Ни одна строка не изменена.');
        $catalog=new Catalog($this->pdo);$this->pdo->beginTransaction();$started=microtime(true);
        try{
            $q=$this->pdo->prepare('SELECT status FROM cp_import WHERE id=? FOR UPDATE');$q->execute([$id]);$status=$q->fetchColumn();if($status==='done'){$this->pdo->commit();return $this->job($id,$actor);}
            $q=$this->pdo->prepare("SELECT * FROM cp_import_row WHERE import_id=? AND status='ready' ORDER BY line_number LIMIT 100 FOR UPDATE");$q->execute([$id]);$batch=$q->fetchAll();
            foreach($batch as $row){$message='';try{
                $input=json_decode($row['input_data'],true,512,JSON_THROW_ON_ERROR);$current=$this->target($input);
                if((int)$current!==(int)$row['target_id'])throw new InvalidArgumentException('Каталог изменился после проверки. Проверьте файл заново.');
                if($current){$record=$catalog->record('product',$current);if(!hash_equals((string)$row['before_hash'],hash('sha256',json_encode($record,JSON_THROW_ON_ERROR))))throw new InvalidArgumentException('Товар изменён после проверки; данные не перезаписаны.');$input['id']=$current;}
                $saved=$catalog->save('product',$input,$actor,$confirmed);$this->pdo->prepare("UPDATE cp_import_row SET target_id=?,status='done',message='' WHERE import_id=? AND line_number=?")->execute([$saved,$id,$row['line_number']]);
            }catch(InvalidArgumentException $e){$message=$e->getMessage();$this->pdo->prepare("UPDATE cp_import_row SET status='error',message=? WHERE import_id=? AND line_number=?")->execute([mb_substr($message,0,1000),$id,$row['line_number']]);}
                if(microtime(true)-$started>=self::STEP_SECONDS)break;
            }
            $q=$this->pdo->prepare("SELECT COUNT(*) FROM cp_import_row WHERE import_id=? AND status='ready'");$q->execute([$id]);$remaining=(int)$q->fetchColumn();$this->pdo->prepare('UPDATE cp_import SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$remaining?'applying':'done',$id]);Factory::audit($this->pdo,$actor,'import',$id,$remaining?'apply_batch':'complete');$this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
        return $this->job($id,$actor);
    }
    public function report(string $id,int $actor,int $page=1):array
    {
        $this->job($id,$actor);$q=$this->pdo->prepare('SELECT line_number,action,target_id,status,message,input_data FROM cp_import_row WHERE import_id=? ORDER BY line_number LIMIT 100 OFFSET '.((max(1,$page)-1)*100));$q->execute([$id]);return $q->fetchAll();
    }
    public function recent(int $actor):array{$q=$this->pdo->prepare('SELECT id,filename,status,created_at FROM cp_import WHERE actor_id=? ORDER BY created_at DESC LIMIT 30');$q->execute([$actor]);return $q->fetchAll();}
    private function summary(string $id):array{$q=$this->pdo->prepare('SELECT status,COUNT(*) n FROM cp_import_row WHERE import_id=? GROUP BY status');$q->execute([$id]);$r=['ready'=>0,'error'=>0,'done'=>0];foreach($q as $row)$r[$row['status']]=(int)$row['n'];return $r;}
    private function target(array $input):?int
    {
        if(isset($input['id']) && trim((string)$input['id'])!==''){
            if(!ctype_digit((string)$input['id'])||(int)$input['id']<1)throw new InvalidArgumentException('ID: требуется положительное целое число.');$q=$this->pdo->prepare('SELECT id FROM products WHERE id=?');$q->execute([(int)$input['id']]);$id=$q->fetchColumn();if(!$id)throw new InvalidArgumentException('Товар с этим ID не найден.');return (int)$id;
        }
        $sku=CatalogSafety::text($input['sku']??'',120,'Артикул',true);$q=$this->pdo->prepare('SELECT id FROM products WHERE sku_norm=? ORDER BY id LIMIT 2');$q->execute([Catalog::normalizeSku($sku)]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)>1)throw new InvalidArgumentException('Артикул неоднозначен: укажите ID существующего товара.');return $ids?(int)$ids[0]:null;
    }
    private function convert(array $input):array
    {
        foreach(['category'=>'category_id','brand'=>'brand_id'] as $field=>$target)if(array_key_exists($field,$input)){
            $value=trim($input[$field]);$id=null;if($value!==''){$table=$field==='category'?'categories':'brands';$q=$this->pdo->prepare('SELECT id FROM '.$table.' WHERE '.(ctype_digit($value)?'id':'name').'=? LIMIT 2');$q->execute([$value]);$matches=$q->fetchAll(PDO::FETCH_COLUMN);if(count($matches)!==1)throw new InvalidArgumentException(($field==='category'?'Категория':'Бренд').': запись не найдена или имя неоднозначно. Укажите ID.');$id=(int)$matches[0];}$input[$target]=$id;unset($input[$field]);
        }
        if(isset($input['in_stock'])){$value=mb_strtolower(trim($input['in_stock']));if(!in_array($value,['0','1','да','нет','true','false','в наличии','под заказ'],true))throw new InvalidArgumentException('Наличие: используйте 1/0, да/нет или в наличии/под заказ.');$input['in_stock']=in_array($value,['1','да','true','в наличии'],true)?1:0;}
        if(array_key_exists('price',$input) && !array_key_exists('price_mode',$input))$input['price_mode']=trim($input['price'])===''?'request':'fixed';
        if(array_key_exists('cross_sku',$input)){$input['cross']=[];foreach(preg_split('/[;\r\n]+/',$input['cross_sku'],-1,PREG_SPLIT_NO_EMPTY) as $sku)$input['cross'][]=['cross_sku'=>trim($sku),'kind'=>'oem'];unset($input['cross_sku']);}
        return $input;
    }
    private function validateRow(array $input,bool $existing):void
    {
        CatalogSafety::text($input['sku']??'',120,'Артикул',true);CatalogSafety::text($input['name']??'',255,'Название',true);
        foreach(['delivery_terms'=>2000,'short_description'=>2000,'seo_h1'=>255,'seo_title'=>255,'seo_description'=>500] as $key=>$max)if(isset($input[$key]))CatalogSafety::text($input[$key],$max,$key);
        $slug=trim((string)($input['slug']??''));if($slug==='')$slug=trim(preg_replace('/[^\p{L}\p{N}]+/u','-',mb_strtolower(($input['sku']??'').'-'.($input['name']??'')))??'','-');
        if(mb_strlen($slug)>180 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_-]*$/uD',$slug) || in_array(mb_strtolower($slug),['admin','catalog','poisk','korzina','stati','robots','sitemap','api','index'],true))throw new InvalidArgumentException('Адрес: недопустимый или зарезервированный slug.');
        $q=$this->pdo->prepare('SELECT id FROM products WHERE slug=? AND id<>?');$q->execute([$slug,(int)($input['id']??0)]);if($q->fetchColumn())throw new InvalidArgumentException('Адрес уже занят другим товаром.');
        foreach(['category_id'=>'categories','brand_id'=>'brands'] as $key=>$table)if(!empty($input[$key])){$q=$this->pdo->prepare('SELECT id FROM '.$table.' WHERE id=?');$q->execute([(int)$input[$key]]);if(!$q->fetchColumn())throw new InvalidArgumentException('Связанная категория или бренд не найдены.');}
        foreach($input['cross']??[] as $row)CatalogSafety::text($row['cross_sku']??'',120,'Кросс-номер',true);
        if(isset($input['price']) && ($input['price_mode']??'request')==='fixed' && (!preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D',(string)$input['price'])||(float)str_replace(',','.',(string)$input['price'])<=0))throw new InvalidArgumentException('Цена: требуется положительное число до двух знаков после запятой.');
        if(isset($input['currency'])&&!preg_match('/^[A-Za-z]{3}$/D',$input['currency']))throw new InvalidArgumentException('Валюта: требуется код из трёх букв.');
        if(isset($input['status'])&&!in_array($input['status'],['draft','published','archived'],true))throw new InvalidArgumentException('Статус: draft, published или archived.');
        if(isset($input['price_mode'])&&!in_array($input['price_mode'],['fixed','request'],true))throw new InvalidArgumentException('Режим цены: fixed или request.');
        if(isset($input['image_url']))CatalogSafety::image($input['image_url']);if(isset($input['body_html']))CatalogSafety::html($input['body_html']);
    }
    private function verifyFile(array $job):void{if(!is_file($job['file_path'])||!hash_equals($job['sha256'],hash_file('sha256',$job['file_path'])))throw new InvalidArgumentException('Исходный файл недоступен или изменён. Загрузите его заново.');}
    private function root():string
    {
        $root=$this->privateRoot??(defined('DIR_STORAGE')?DIR_STORAGE.'catpart-imports':(getenv('CATPART_ADMIN_RUNTIME')?:'').'/private/imports');
        if($root==='/private/imports' || $root==='')throw new \RuntimeException('Приватная папка импорта не настроена.');
        if(!is_dir($root)&&!mkdir($root,0700,true))throw new \RuntimeException('Приватная папка импорта недоступна.');$real=realpath($root);$doc=($_SERVER['DOCUMENT_ROOT']??'')!==''?realpath($_SERVER['DOCUMENT_ROOT']):false;
        if($real===false||($doc!==false && str_starts_with(str_replace('\\','/',$real).'/',rtrim(str_replace('\\','/',$doc),'/').'/')))throw new \RuntimeException('Файлы импорта должны находиться вне публичного каталога.');return $real;
    }
    private function validateBook(string $path):void
    {
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('XLSX: файл не является корректной книгой.');$total=0;$started=microtime(true);
        try{if($zip->numFiles>2000)throw new InvalidArgumentException('XLSX: слишком много файлов внутри книги.');for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$name=$s['name'];$total+=(int)$s['size'];if(str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\')||$total>64*1024*1024||((int)$s['size']>1024*1024 && (int)$s['size']>max(1,(int)$s['comp_size'])*200))throw new InvalidArgumentException('XLSX: небезопасный или чрезмерно сжатый архив.');
                if($name==='xl/sharedStrings.xml' && (int)$s['size']>8*1024*1024)throw new InvalidArgumentException('XLSX: словарь строк больше 8 МиБ. Разделите файл для памяти PHP 128 МиБ.');
                if(str_ends_with(strtolower($name),'.xml') || str_ends_with(strtolower($name),'.rels')){
                    $previous=libxml_use_internal_errors(true);libxml_clear_errors();$reader=new XMLReader();$line=0;
                    try{
                        if(!$reader->open('zip://'.$path.'#'.$name,null,LIBXML_NONET))throw new InvalidArgumentException('XLSX: неверный XML книги.');
                        while($reader->read()){
                            if($reader->nodeType===XMLReader::DOC_TYPE || $reader->nodeType===XMLReader::ENTITY_REF)throw new InvalidArgumentException('XLSX: внешние сущности XML запрещены.');
                            if($reader->nodeType===XMLReader::ELEMENT && $reader->localName==='row')$line=(int)($reader->getAttribute('r')?:$line+1);
                            if(preg_match('~^xl/worksheets/~',$name) && $reader->nodeType===XMLReader::ELEMENT && $reader->localName==='f')throw new InvalidArgumentException('XLSX, строка '.$line.': формулы не поддерживаются. Сохраните значения текстом.');
                            if(microtime(true)-$started>20)throw new InvalidArgumentException('XLSX: проверка книги превысила 20 секунд. Разделите файл; данные не обрезаются.');
                        }
                        if(libxml_get_errors())throw new InvalidArgumentException('XLSX, строка '.$line.': повреждён XML книги.');
                    }finally{$reader->close();libxml_clear_errors();libxml_use_internal_errors($previous);}
                }}
            if($zip->locateName('[Content_Types].xml')===false || $zip->locateName('xl/workbook.xml')===false)throw new InvalidArgumentException('XLSX: отсутствует структура книги.');
        }finally{$zip->close();}
    }
    private function rows(string $path):\Generator
    {
        if(strtolower(pathinfo($path,PATHINFO_EXTENSION))==='csv'){
            $stream=fopen($path,'rb');if(!$stream)throw new InvalidArgumentException('CSV: не удалось прочитать файл.');$sample=(string)fgets($stream);rewind($stream);$delimiters=[',',';',"\t"];$delimiter=',';$most=0;foreach($delimiters as $d){$n=count(str_getcsv($sample,$d,'"',''));if($n>$most){$most=$n;$delimiter=$d;}}
            try{$number=0;while(($row=fgetcsv($stream,0,$delimiter,'"',''))!==false){$number++;$out=[];foreach($row as $cell){$cell=(string)$cell;if($number===1)$cell=preg_replace('/^\xEF\xBB\xBF/','',$cell)??$cell;if(!mb_check_encoding($cell,'UTF-8'))$cell=mb_convert_encoding($cell,'UTF-8','Windows-1251');if(strlen($cell)>2*1024*1024)throw new InvalidArgumentException('Строка '.$number.': слишком большое содержимое ячейки.');$out[]=$cell;}if(count($out)>200)throw new InvalidArgumentException('Строка '.$number.': более 200 колонок.');yield $number=>$out;}}finally{fclose($stream);}return;
        }
        // Adaptation of CatPart's streaming XlsxReader; bounded ZIP was validated before storage.
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('XLSX: файл не читается.');$sheets=[];for($i=0;$i<$zip->numFiles;$i++){$name=$zip->getNameIndex($i);if(preg_match('~^xl/worksheets/sheet\d+\.xml$~D',$name))$sheets[]=$name;}natsort($sheets);$sheet=$this->firstSheet($zip,$sheets);$sharedXml=$zip->getFromName('xl/sharedStrings.xml');$zip->close();if(!$sheets)throw new InvalidArgumentException('XLSX: лист не найден.');
        $shared=[];if($sharedXml!==false){$reader=new XMLReader();if(!$reader->XML($sharedXml,'UTF-8',LIBXML_NONET))throw new InvalidArgumentException('XLSX: неверный словарь строк.');while($reader->read())if($reader->nodeType===XMLReader::ELEMENT&&$reader->localName==='si'){$d=new \DOMDocument();$d->loadXML($reader->readOuterXml(),LIBXML_NONET);$value='';foreach($d->getElementsByTagName('t') as $t)$value.=$t->textContent;$shared[]=$value;}$reader->close();}
        $reader=new XMLReader();if(!$reader->open('zip://'.$path.'#'.$sheet,'UTF-8',LIBXML_NONET))throw new InvalidArgumentException('XLSX: лист не читается.');$index=0;
        try{while($reader->read())if($reader->nodeType===XMLReader::ELEMENT&&$reader->localName==='row'){$index++;$number=(int)($reader->getAttribute('r')?:$index);if($index===1 && $number!==1)throw new InvalidArgumentException('XLSX: заголовки должны находиться в первой строке листа.');$rawRow=$reader->readOuterXml();if(strlen($rawRow)>2*1024*1024)throw new InvalidArgumentException('Строка '.$number.': более 2 МиБ данных. Разделите длинное содержимое.');$d=new \DOMDocument();if(!$d->loadXML($rawRow,LIBXML_NONET))throw new InvalidArgumentException('Строка '.$number.': неверный XML.');$cells=[];foreach($d->getElementsByTagName('c') as $cell){$reference=$cell->getAttribute('r');preg_match('/^([A-Z]+)/',$reference,$m);$column=0;foreach(str_split($m[1]??'A') as $letter)$column=$column*26+ord($letter)-64;$column--;if($column>199)throw new InvalidArgumentException('Строка '.$number.': более 200 колонок.');$type=$cell->getAttribute('t');$value='';if($type==='inlineStr'){foreach($cell->getElementsByTagName('t') as $t)$value.=$t->textContent;}else{$raw=$cell->getElementsByTagName('v')->item(0)?->textContent??'';if($type==='s' && (!ctype_digit($raw)||!array_key_exists((int)$raw,$shared)))throw new InvalidArgumentException('Строка '.$number.': неверный индекс строки XLSX.');$value=$type==='s'?$shared[(int)$raw]:$raw;}$cells[$column]=$value;}
                if($cells){$out=array_fill(0,max(array_keys($cells))+1,'');foreach($cells as $column=>$value)$out[$column]=$value;yield $number=>$out;}
            }}finally{$reader->close();}
    }
    private function firstSheet(ZipArchive $zip,array $candidates):string
    {
        if(!$candidates)throw new InvalidArgumentException('XLSX: лист не найден.');
        $workbook=$zip->getFromName('xl/workbook.xml');$relations=$zip->getFromName('xl/_rels/workbook.xml.rels');
        if($workbook===false || $relations===false)return (string)reset($candidates);
        if(strlen($workbook)>4*1024*1024 || strlen($relations)>4*1024*1024)throw new InvalidArgumentException('XLSX: чрезмерно большой указатель листов.');
        $d=new \DOMDocument();if(!$d->loadXML($workbook,LIBXML_NONET))throw new InvalidArgumentException('XLSX: неверное описание листов.');$first=$d->getElementsByTagNameNS('*','sheet')->item(0);if(!$first)throw new InvalidArgumentException('XLSX: лист не найден.');
        $id=$first->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$d=new \DOMDocument();if(!$d->loadXML($relations,LIBXML_NONET))throw new InvalidArgumentException('XLSX: неверные связи листов.');
        foreach($d->getElementsByTagNameNS('*','Relationship') as $rel)if($rel->getAttribute('Id')===$id){$target=$rel->getAttribute('Target');$target=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;if($rel->getAttribute('TargetMode')==='External' || !in_array($target,$candidates,true))throw new InvalidArgumentException('XLSX: небезопасная ссылка на лист.');return $target;}
        throw new InvalidArgumentException('XLSX: первая вкладка книги не найдена.');
    }
}
