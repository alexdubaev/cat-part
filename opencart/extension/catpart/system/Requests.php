<?php
declare(strict_types=1);
namespace Catpart;

use PDO;
use Throwable;
use InvalidArgumentException;

/** One request registry: native OpenCart orders, linked immutable CatPart snapshots. */
final class Requests
{
    public const MAX_QUANTITY = 999999;
    public const STATUSES = [101=>'Новый',102=>'В работе',103=>'Ожидает согласования',104=>'Согласован',105=>'Завершён',106=>'Отменён'];
    public function __construct(private PDO $pdo) {}

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_request_key (request_key CHAR(64) PRIMARY KEY,kind VARCHAR(12) NOT NULL,entity_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_order (order_id INT NOT NULL PRIMARY KEY,number VARCHAR(40) NOT NULL UNIQUE,source VARCHAR(300) NOT NULL,customer LONGTEXT NOT NULL,snapshot LONGTEXT NOT NULL,manager_id INT NULL,status_id INT NOT NULL DEFAULT 101,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,KEY status_list(status_id,order_id),KEY manager_list(manager_id,order_id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_lead (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,number VARCHAR(40) NULL UNIQUE,type VARCHAR(60) NOT NULL,source VARCHAR(500) NOT NULL,name VARCHAR(120) NOT NULL,phone VARCHAR(40) NOT NULL,email VARCHAR(254) NOT NULL,message TEXT NOT NULL,status_id INT NOT NULL DEFAULT 101,manager_id INT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,KEY status_list(status_id,id),KEY phone_search(phone),KEY email_search(email)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_request_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(12) NOT NULL,entity_id BIGINT UNSIGNED NOT NULL,actor_id INT NOT NULL,status_id INT NOT NULL,manager_id INT NULL,note TEXT NOT NULL,quotation LONGTEXT NULL,created_at DATETIME NOT NULL,KEY entity_history(kind,entity_id,id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $q=$pdo->prepare('INSERT IGNORE INTO oc_order_status(order_status_id,language_id,name) VALUES(?,?,?)');
        foreach ($pdo->query('SELECT language_id FROM oc_language')->fetchAll(PDO::FETCH_COLUMN) as $language) foreach(self::STATUSES as $id=>$name) $q->execute([$id,$language,$name]);
        if(!(int)$pdo->query("SELECT COUNT(*) FROM oc_currency WHERE code='RUB'")->fetchColumn())$pdo->exec("INSERT INTO oc_currency(title,code,symbol_left,symbol_right,decimal_place,value,status,date_modified) VALUES('Рубль','RUB','','₽',2,1,1,UTC_TIMESTAMP())");
        Outbox::migrate($pdo);
    }

    public static function quantity(mixed $value, bool $allowZero=false): int
    {
        $raw=is_int($value)?(string)$value:(is_string($value)?$value:'');
        if (!preg_match('/^(0|[1-9][0-9]{0,5})$/D',$raw) || (int)$raw>self::MAX_QUANTITY || (!$allowZero && (int)$raw===0)) {
            throw new InvalidArgumentException('Количество должно быть целым числом от '.($allowZero?'0':'1').' до '.self::MAX_QUANTITY.'.');
        }
        return (int)$raw;
    }

    public static function customer(array $input): array
    {
        $out=[];
        foreach (['name'=>120,'phone'=>40,'email'=>254,'comment'=>4000] as $key=>$max) {
            $value=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',(string)($input[$key]??''))??'');
            if ($key!=='comment') $value=preg_replace('/[\r\n]+/u',' ',$value)??'';
            if (mb_strlen($value)>$max) throw new InvalidArgumentException('Поле «'.(['name'=>'Имя','phone'=>'Телефон','email'=>'Email','comment'=>'Комментарий'][$key]).'» слишком длинное (до '.$max.' символов).');
            $out[$key]=$value;
        }
        if ($out['name']==='') throw new InvalidArgumentException('Укажите, как к вам обращаться.');
        if (strlen(preg_replace('/\D/','',$out['phone'])??'')<10) throw new InvalidArgumentException('В телефоне должно быть не меньше 10 цифр.');
        if ($out['email']!=='' && !filter_var($out['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Проверьте адрес электронной почты.');
        if (empty($input['consent'])) throw new InvalidArgumentException('Отметьте согласие на обработку персональных данных.');
        return $out;
    }
    public static function fieldErrors(string $message): array
    {
        $lower=mb_strtolower($message);
        foreach(['phone'=>['телефон'],'email'=>['email','электронной'],'consent'=>['согласие'],'name'=>['имя','как к вам'],'comment'=>['комментарий']] as $field=>$needles)foreach($needles as $needle)if(str_contains($lower,$needle))return [$field=>$message];
        return [];
    }

    private function key(string $owner,string $token,string $kind): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$owner) || !preg_match('/^[a-f0-9]{64}$/D',$token)) throw new InvalidArgumentException('Форма устарела. Обновите страницу и повторите.');
        return hash('sha256',$kind."\0".$owner."\0".$token);
    }

    public function receipt(string $owner,string $token,string $kind='order'): ?array
    {
        $q=$this->pdo->prepare('SELECT entity_id FROM cp_request_key WHERE request_key=? AND kind=?');$q->execute([$this->key($owner,$token,$kind),$kind]);$id=$q->fetchColumn();
        if ($id===false) return null;
        $row=$kind==='order'?$this->order((int)$id):$this->lead((int)$id);$submitted=[];
        if($kind==='order')foreach($row['snapshot'] as $line)$submitted[(string)$line['product_id']]=$line['quantity'];
        return ['id'=>(int)$id,'number'=>$row['number'],'replayed'=>true,'submitted'=>$submitted];
    }

    public function submitOrder(string $owner,string $token,array $input,array $cart,string $source='/korzina'): array
    {
        return $this->once($owner,$token,'order',function()use($input,$cart,$source):array {
            $customer=self::customer($input);
            if (!$cart) throw new InvalidArgumentException('Корзина пуста. Добавьте хотя бы одну позицию.');
            $lines=[];$total='0.0000';
            foreach ($cart as $id=>$rawQty) {
                if (!ctype_digit((string)$id) || (int)$id<1) throw new InvalidArgumentException('В корзине некорректная позиция. Обновите страницу.');
                $qty=self::quantity($rawQty);
                $q=$this->pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');$q->execute([(int)$id]);$p=$q->fetch();
                if (!$p || !(int)$p['is_active']) throw new InvalidArgumentException('Позиция №'.(int)$id.' больше недоступна. Удалите её из корзины или уточните у менеджера.');
                $conditions=$this->conditions((int)$id);
                $price=$p['price']!==null && (float)$p['price']>0 && (($conditions['price_mode']??'')!=='request')?(string)$p['price']:null;
                $lines[]=['product_id'=>(int)$p['id'],'sku'=>(string)$p['sku'],'name'=>(string)$p['name'],'quantity'=>$qty,'price'=>$price,'currency'=>(string)($p['currency']?:'RUB'),'in_stock'=>(bool)$p['in_stock'],'delivery_terms'=>(string)($conditions['delivery_terms']??$conditions['delivery']??''),'requires_confirmation'=>true,'price_on_request'=>$price===null];
                if ($price!==null) {
                    $lineTotal=(float)$price*$qty;
                    if (!is_finite($lineTotal) || $lineTotal>99999999999) throw new InvalidArgumentException('Сумма позиции слишком велика. Уточните количество у менеджера.');
                    $total=number_format((float)$total+$lineTotal,4,'.','');
                }
            }
            if ((float)$total>99999999999) throw new InvalidArgumentException('Сумма запроса слишком велика. Уточните позиции у менеджера.');
            $currencies=array_values(array_unique(array_column($lines,'currency')));$currencyCode=count($currencies)===1?$currencies[0]:'';
            $hasUnknown=in_array(null,array_column($lines,'price'),true);
            $nativeTotal=$currencyCode!=='' && !$hasUnknown?$total:'0.0000';
            $currencyQuery=$this->pdo->prepare('SELECT currency_id FROM oc_currency WHERE code=? LIMIT 1');$currencyQuery->execute([$currencyCode]);$currencyId=(int)$currencyQuery->fetchColumn();
            // Native columns are compatibility projections; the original full contact/SKU is retained below.
            $q=$this->pdo->prepare("INSERT INTO oc_order(store_id,store_name,store_url,customer_id,customer_group_id,firstname,lastname,email,telephone,comment,total,order_status_id,language_id,language_code,currency_id,currency_code,currency_value,commission,shipping_address_id,date_added,date_modified) VALUES(0,'CatPart','',0,0,?,'',?,?,?, ?,101,1,'ru-ru',?,?,1,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $q->execute([mb_substr($customer['name'],0,32),mb_strlen($customer['email'])<=96?$customer['email']:'',mb_substr($customer['phone'],0,32),$customer['comment'],$nativeTotal,$currencyId,$currencyCode]);
            $id=(int)$this->pdo->lastInsertId();$number='CP-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            $q=$this->pdo->prepare('INSERT INTO oc_order_product(order_id,product_id,master_id,name,model,quantity,price,total,tax,reward) VALUES(?,?,0,?,?,?,?,?,0,0)');
            foreach($lines as $line)$q->execute([$id,$line['product_id'],mb_substr($line['name'],0,255),mb_substr($line['sku'],0,64),$line['quantity'],$line['price']??0,$line['price']===null?0:number_format((float)$line['price']*$line['quantity'],4,'.','')]);
            $q=$this->pdo->prepare('INSERT INTO cp_order(order_id,number,source,customer,snapshot,status_id,created_at,updated_at) VALUES(?,?,?,?,?,101,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute([$id,$number,self::text($source,300),self::json($customer),self::json($lines)]);
            $this->history('order',$id,0,101,null,'Запрос принят.',null);
            (new Outbox($this->pdo))->enqueue('order',$id);
            return ['id'=>$id,'number'=>$number,'replayed'=>false,'submitted'=>$cart];
        });
    }

    private function conditions(int $id): array
    {
        // Catalog metadata is optional until its explicit migration has run.
        try {
            $q=$this->pdo->prepare("SELECT data FROM cp_catalog_meta WHERE entity='product' AND entity_id=?");$q->execute([$id]);$data=$q->fetchColumn();
            return $data===false?[]:(json_decode((string)$data,true,512,JSON_THROW_ON_ERROR)?:[]);
        } catch (\PDOException $e) { if (in_array((string)$e->getCode(),['42S02','42S22'],true))return [];throw $e; }
    }

    public function submitLead(string $owner,string $token,array $input,string $type,string $source): array
    {
        return $this->once($owner,$token,'lead',function()use($input,$type,$source):array {
            $customer=self::customer($input);
            $q=$this->pdo->prepare('INSERT INTO cp_lead(type,source,name,phone,email,message,status_id,created_at,updated_at) VALUES(?,?,?,?,?,?,101,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute([self::text($type,60),self::text($source,500),$customer['name'],$customer['phone'],$customer['email'],$customer['comment']]);
            $id=(int)$this->pdo->lastInsertId();$number='CF-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            $q=$this->pdo->prepare('UPDATE cp_lead SET number=? WHERE id=?');$q->execute([$number,$id]);
            $this->history('lead',$id,0,101,null,'Обращение принято.',null);(new Outbox($this->pdo))->enqueue('lead',$id);
            return ['id'=>$id,'number'=>$number,'replayed'=>false];
        });
    }

    private function once(string $owner,string $token,string $kind,callable $create): array
    {
        $key=$this->key($owner,$token,$kind);$lock='cp-req-'.substr($key,0,48);
        $q=$this->pdo->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new \RuntimeException('Запрос ещё обрабатывается. Повторите отправку той же формы.');
        try {
            if ($existing=$this->receipt($owner,$token,$kind))return $existing;
            $this->pdo->beginTransaction();
            try {
                $result=$create();
                $q=$this->pdo->prepare('INSERT INTO cp_request_key(request_key,kind,entity_id,created_at) VALUES(?,?,?,UTC_TIMESTAMP())');$q->execute([$key,$kind,$result['id']]);
                Factory::audit($this->pdo,0,$kind,(string)$result['id'],'submit');$this->pdo->commit();return $result;
            } catch(Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e; }
        } finally { $q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]); }
    }

    public function order(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM cp_order WHERE order_id=?');$q->execute([$id]);$row=$q->fetch();
        if (!$row)return null;$row['customer']=json_decode($row['customer'],true,512,JSON_THROW_ON_ERROR);$row['snapshot']=json_decode($row['snapshot'],true,512,JSON_THROW_ON_ERROR);return $row;
    }
    public function lead(int $id): ?array { $q=$this->pdo->prepare('SELECT * FROM cp_lead WHERE id=?');$q->execute([$id]);return $q->fetch()?:null; }
    public function historyRows(string $kind,int $id): array { $q=$this->pdo->prepare('SELECT h.*,u.firstname AS actor_name FROM cp_request_history h LEFT JOIN oc_user u ON u.user_id=h.actor_id WHERE kind=? AND entity_id=? ORDER BY h.id DESC');$q->execute([$kind,$id]);return $q->fetchAll(); }
    public function managers(?string $kind=null): array
    {
        $users=$this->pdo->query('SELECT u.user_id,u.firstname,u.lastname,u.username,g.permission FROM oc_user u JOIN oc_user_group g ON g.user_group_id=u.user_group_id WHERE u.status=1 ORDER BY u.firstname,u.user_id')->fetchAll();
        return array_values(array_filter($users,static function(array $u)use($kind):bool{$permissions=json_decode($u['permission'],true)?:[];$areas=$kind==='order'?['orders']:($kind==='lead'?['leads']:['orders','leads']);foreach($areas as $area)if(in_array('extension/catpart/module/'.$area,$permissions['modify']??[],true))return true;return false;}));
    }

    public function listing(string $kind,array $filters=[]): array
    {
        if (!in_array($kind,['order','lead'],true))throw new InvalidArgumentException('Неизвестный реестр.');
        $table=$kind==='order'?'cp_order':'cp_lead';$id=$kind==='order'?'order_id':'id';$where=[];$args=[];
        $status=(int)($filters['status']??0);if($status && isset(self::STATUSES[$status])){$where[]='status_id=?';$args[]=$status;}
        if((int)($filters['manager']??0)>0){$where[]='manager_id=?';$args[]=(int)$filters['manager'];}
        if($kind==='lead' && trim((string)($filters['type']??''))!==''){$where[]='type=?';$args[]=self::text((string)$filters['type'],60);}
        $needle=trim((string)($filters['q']??''));if($needle!==''){$needle='%'.strtr($needle,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]=$kind==='order'?"(number LIKE ? ESCAPE '!' OR customer LIKE ? ESCAPE '!')":"(number LIKE ? ESCAPE '!' OR CONCAT(name,' ',phone,' ',email) LIKE ? ESCAPE '!')";$args[]=$needle;$args[]=$needle;}
        $condition=$where?' WHERE '.implode(' AND ',$where):'';
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM '.$table.$condition);$q->execute($args);$total=(int)$q->fetchColumn();$page=max(1,(int)($filters['page']??1));$offset=($page-1)*30;
        $q=$this->pdo->prepare('SELECT * FROM '.$table.$condition.' ORDER BY '.$id.' DESC LIMIT 30 OFFSET '.$offset);$q->execute($args);$rows=$q->fetchAll();
        if($kind==='order')foreach($rows as &$r)$r['customer']=json_decode($r['customer'],true,512,JSON_THROW_ON_ERROR);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/30))];
    }

    public function update(string $kind,int $id,array $input,int $actor): void
    {
        if (!in_array($kind,['order','lead'],true))throw new InvalidArgumentException('Неизвестный реестр.');
        $status=(int)($input['status_id']??0);if(!isset(self::STATUSES[$status]))throw new InvalidArgumentException('Выберите допустимый статус.');
        $manager=(int)($input['manager_id']??0);if($manager && !in_array($manager,array_map(static fn($u)=>(int)$u['user_id'],$this->managers($kind)),true))throw new InvalidArgumentException('Сотрудник не имеет доступа к обработке обращений.');
        $note=self::text((string)($input['note']??''),4000);
        $quotation=null;
        if($kind==='order'){
            $quotation=['text'=>self::text((string)($input['quotation']??''),4000),'lines'=>[]];$order=$this->order($id);if(!$order)throw new InvalidArgumentException('Запись не найдена.');
            $allowed=array_column($order['snapshot'],'product_id');
            foreach((array)($input['quote_lines']??[]) as $pid=>$line){
                if(!in_array((int)$pid,$allowed,true)||!is_array($line))throw new InvalidArgumentException('Неизвестная позиция расценки.');
                $price=trim(str_replace(',','.',(string)($line['price']??'')));if($price==='')continue;
                if(!preg_match('/^(0|[1-9][0-9]{0,10})(\.[0-9]{1,4})?$/D',$price))throw new InvalidArgumentException('Проверьте цену расценки: положительное число, до четырёх знаков после запятой.');
                $currency=strtoupper((string)($line['currency']??'RUB'));if(!preg_match('/^[A-Z]{3}$/D',$currency))throw new InvalidArgumentException('Валюта должна содержать три латинские буквы.');
                $quotation['lines'][]=['product_id'=>(int)$pid,'price'=>$price,'currency'=>$currency];
            }
            if($quotation['text']==='' && !$quotation['lines'])$quotation=null;
        }
        $this->pdo->beginTransaction();try {
            $table=$kind==='order'?'cp_order':'cp_lead';$pk=$kind==='order'?'order_id':'id';
            $q=$this->pdo->prepare("SELECT status_id FROM $table WHERE $pk=? FOR UPDATE");$q->execute([$id]);if($q->fetchColumn()===false)throw new InvalidArgumentException('Запись не найдена.');
            $q=$this->pdo->prepare("UPDATE $table SET status_id=?,manager_id=?,updated_at=UTC_TIMESTAMP() WHERE $pk=?");$q->execute([$status,$manager?:null,$id]);
            if($kind==='order'){$q=$this->pdo->prepare('UPDATE oc_order SET order_status_id=?,date_modified=UTC_TIMESTAMP() WHERE order_id=?');$q->execute([$status,$id]);}
            $this->history($kind,$id,$actor,$status,$manager?:null,$note,$quotation);Factory::audit($this->pdo,$actor,$kind,(string)$id,'process',['status'=>$status,'manager'=>$manager]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function history(string $kind,int $id,int $actor,int $status,?int $manager,string $note,?array $quotation): void
    {
        $q=$this->pdo->prepare('INSERT INTO cp_request_history(kind,entity_id,actor_id,status_id,manager_id,note,quotation,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())');$q->execute([$kind,$id,$actor,$status,$manager,$note,$quotation===null?null:self::json($quotation)]);
        if($kind==='order'){$q=$this->pdo->prepare('INSERT INTO oc_order_history(order_id,order_status_id,notify,comment,date_added) VALUES(?,?,0,?,UTC_TIMESTAMP())');$q->execute([$id,$status,$note]);}
    }
    public function stats(): array
    {
        return ['orders'=>(int)$this->pdo->query('SELECT COUNT(*) FROM cp_order')->fetchColumn(),'leads'=>(int)$this->pdo->query('SELECT COUNT(*) FROM cp_lead')->fetchColumn(),'new_orders'=>(int)$this->pdo->query('SELECT COUNT(*) FROM cp_order WHERE status_id=101')->fetchColumn(),'new_leads'=>(int)$this->pdo->query('SELECT COUNT(*) FROM cp_lead WHERE status_id=101')->fetchColumn(),'notification_errors'=>(int)$this->pdo->query("SELECT COUNT(*) FROM cp_outbox WHERE state='error'")->fetchColumn(),'order_statuses'=>$this->pdo->query('SELECT status_id,COUNT(*) AS amount FROM cp_order GROUP BY status_id')->fetchAll(),'lead_statuses'=>$this->pdo->query('SELECT status_id,COUNT(*) AS amount FROM cp_lead GROUP BY status_id')->fetchAll()];
    }
    private static function text(string $text,int $max): string { $text=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$text)??'');if(mb_strlen($text)>$max)throw new InvalidArgumentException('Текст слишком длинный (до '.$max.' символов).');return $text; }
    private static function json(array $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
}
