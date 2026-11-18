<?php
declare(strict_types=1);
namespace Catpart;
use PDO;
use Throwable;

/** Durable jobs with an expiring lease; transport is deliberately separate from acceptance. */
final class Outbox
{
    public const STATES=['queued'=>'В очереди','processing'=>'Передаётся','captured'=>'Сохранено в локальный перехват','accepted'=>'Принято почтовым сервером','error'=>'Ошибка уведомления'];
    private const ERRORS=['no_recipient'=>'Не настроен получатель уведомлений.','invalid_sender'=>'Проверьте адрес отправителя в настройках.','capture_directory'=>'Каталог локального перехвата недоступен или находится в публичной раздаче.','capture_write'=>'Не удалось записать письмо в локальный перехват.','transport_error'=>'Транспорт уведомлений недоступен. Запись обращения сохранена.'];
    public function __construct(private PDO $pdo) {}
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_outbox (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(12) NOT NULL,entity_id BIGINT UNSIGNED NOT NULL,state VARCHAR(20) NOT NULL DEFAULT 'queued',attempts INT NOT NULL DEFAULT 0,error_code VARCHAR(60) NOT NULL DEFAULT '',message_id VARCHAR(150) NOT NULL,available_at DATETIME NOT NULL,lease_until DATETIME NULL,lease_token CHAR(32) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY one_notification(kind,entity_id),KEY due_jobs(state,available_at,id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
    }
    public function enqueue(string $kind,int $id): void
    {
        if(!in_array($kind,['order','lead'],true) || $id<1)throw new \InvalidArgumentException('Некорректное уведомление.');
        $message='<catpart-'.bin2hex(random_bytes(16)).'@cat-part.ru>';
        $q=$this->pdo->prepare("INSERT IGNORE INTO cp_outbox(kind,entity_id,message_id,available_at,created_at,updated_at) VALUES(?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())");$q->execute([$kind,$id,$message]);
    }
    public function forEntity(string $kind,int $id): ?array { $q=$this->pdo->prepare('SELECT * FROM cp_outbox WHERE kind=? AND entity_id=?');$q->execute([$kind,$id]);return $q->fetch()?:null; }
    public function listing(): array { return $this->pdo->query('SELECT * FROM cp_outbox ORDER BY id DESC LIMIT 100')->fetchAll(); }
    public static function errorText(string $code): string { return self::ERRORS[$code]??self::ERRORS['transport_error']; }
    public function retry(int $jobId,int $actor,?string $kind=null,?int $entityId=null): void
    {
        $q=$this->pdo->prepare('SELECT * FROM cp_outbox WHERE id=?');$q->execute([$jobId]);$job=$q->fetch();
        if(!$job || ($kind!==null && ($job['kind']!==$kind || (int)$job['entity_id']!==$entityId)))throw new \InvalidArgumentException('Уведомление не найдено.');
        if($job['state']!=='error')return;
        $q=$this->pdo->prepare("UPDATE cp_outbox SET state='queued',attempts=0,error_code='',available_at=UTC_TIMESTAMP(),lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND state='error'");$q->execute([$jobId]);
        if($q->rowCount())Factory::audit($this->pdo,$actor,$job['kind'],(string)$job['entity_id'],'notification_retry');
    }
    /** Callable accepts the private job DTO and returns captured/accepted or throws a safe code. */
    public function run(callable $transport,int $limit=20,array $onlyJobs=[]): array
    {
        $limit=max(1,min(100,$limit));$lock='cp-outbox-'.substr(hash('sha256',(string)$this->pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)return ['processed'=>0,'errors'=>0,'busy'=>true];
        $done=0;$errors=0;$onlyJobs=array_values(array_unique(array_filter(array_map('intval',$onlyJobs),static fn($id)=>$id>0)));
        $filter=$onlyJobs?' AND id IN ('.implode(',',$onlyJobs).')':'';
        try {
            $this->pdo->exec("UPDATE cp_outbox SET state='error',error_code='transport_error',lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE state='processing' AND lease_until<UTC_TIMESTAMP() AND attempts>=3".$filter);
            for($i=0;$i<$limit;$i++){
                $this->pdo->beginTransaction();
                try {
                    $job=$this->pdo->query("SELECT * FROM cp_outbox WHERE ((state IN ('queued','error') AND available_at<=UTC_TIMESTAMP() AND attempts<3) OR (state='processing' AND lease_until<UTC_TIMESTAMP() AND attempts<3))".$filter." ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
                    if(!$job){$this->pdo->commit();break;}
                    $lease=bin2hex(random_bytes(16));$q=$this->pdo->prepare("UPDATE cp_outbox SET state='processing',attempts=attempts+1,lease_token=?,lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 300 SECOND),updated_at=UTC_TIMESTAMP() WHERE id=?");$q->execute([$lease,$job['id']]);$this->pdo->commit();
                }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
                $state='error';$error='';
                try { $state=$transport($job);if(!in_array($state,['captured','accepted'],true))throw new \RuntimeException('transport_error'); }
                catch(Throwable $e){$state='error';$error=isset(self::ERRORS[$e->getMessage()])?$e->getMessage():'transport_error';$errors++;}
                $delay=(int)$job['attempts']===0?60:300;
                $q=$this->pdo->prepare("UPDATE cp_outbox SET state=?,error_code=?,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL $delay SECOND),lease_until=NULL,lease_token=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND lease_token=?");$q->execute([$state,$error,$job['id'],$lease]);$done++;
            }
        }finally{$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
        return ['processed'=>$done,'errors'=>$errors,'busy'=>false];
    }
    public function captureTransport(string $directory,string $publicRoot): callable
    {
        return function(array $job)use($directory,$publicRoot):string {
            if(!is_dir($directory) && !@mkdir($directory,0700,true))throw new \RuntimeException('capture_directory');
            $real=realpath($directory);$public=realpath($publicRoot);
            if(!$real || !$public)throw new \RuntimeException('capture_directory');
            $norm=static fn(string $p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
            if($norm($real)===$norm($public) || str_starts_with($norm($real),$norm($public).'/'))throw new \RuntimeException('capture_directory');
            $message=$this->message($job);
            [$fromHeader,$headers]=explode("\r\n",$message['headers'],2);
            $mime='To: '.$message['to']."\r\n".$fromHeader."\r\nSubject: ".$message['subject']."\r\n".$headers."\r\n\r\n".$message['body'];
            $path=$real.'/'.hash('sha256',$job['message_id']).'.eml';
            if(is_file($path)){
                if(!hash_equals(hash('sha256',$mime),hash_file('sha256',$path)))throw new \RuntimeException('capture_write');
                return 'captured';
            }
            $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
            if(file_put_contents($temp,$mime,LOCK_EX)!==strlen($mime)){@unlink($temp);throw new \RuntimeException('capture_write');}
            if(!@rename($temp,$path)){@unlink($temp);throw new \RuntimeException('capture_write');}
            @chmod($path,0600);return 'captured';
        };
    }
    /** True means the configured MTA accepted the message, not that an inbox received it. */
    public function mailTransport(array $mailDefaults=[],array $siteDefaults=[],?callable $sender=null):callable
    {
        $sender??=static fn(string $to,string $subject,string $body,string $headers):bool=>@mail($to,$subject,$body,$headers);
        return function(array $job)use($mailDefaults,$siteDefaults,$sender):string {
            $message=$this->message($job,$mailDefaults,$siteDefaults);
            if($sender($message['to'],$message['subject'],$message['body'],$message['headers'])!==true)throw new \RuntimeException('transport_error');
            return 'accepted';
        };
    }
    /** Private DTO: only validated configuration reaches headers; request data stays encoded in the body. */
    private function message(array $job,array $mailDefaults=[],array $siteDefaults=[]):array
    {
        if(!in_array($job['kind']??null,['order','lead'],true) || (int)($job['entity_id']??0)<1
            || !preg_match('/^<catpart-[a-f0-9]{32}@cat-part\.ru>$/D',(string)($job['message_id']??'')))throw new \RuntimeException('transport_error');
        $mail=array_replace($mailDefaults,Factory::settings($this->pdo,'mail'));
        $site=array_replace($siteDefaults,Factory::settings($this->pdo,'site'));
        $recipients=array_values(array_unique(array_filter((array)($mail['notify']??[$site['email']??'']),static fn($e)=>is_string($e) && (bool)filter_var($e,FILTER_VALIDATE_EMAIL))));
        if(!$recipients)throw new \RuntimeException('no_recipient');
        $from=(string)($mail['from_email']??$site['email']??'');if(!filter_var($from,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('invalid_sender');
        $fromName=preg_replace('/[\x00-\x1F\x7F]/',' ',(string)($mail['from_name']??$site['company']??'CatPart'));
        $requests=new Requests($this->pdo);$row=$job['kind']==='order'?$requests->order((int)$job['entity_id']):$requests->lead((int)$job['entity_id']);
        if(!$row)throw new \RuntimeException('transport_error');
        $customer=$job['kind']==='order'?$row['customer']:['name'=>$row['name'],'phone'=>$row['phone'],'email'=>$row['email'],'comment'=>$row['message']];
        $body=$row['number']."\nИмя: ".$customer['name']."\nТелефон: ".$customer['phone']."\nEmail: ".$customer['email']."\nКомментарий: ".$customer['comment']."\nИсточник: ".$row['source']."\n";
        if($job['kind']==='order')foreach($row['snapshot'] as $line)$body.=$line['sku'].' — '.$line['name'].' × '.$line['quantity']."\n";
        $body.="\nЦена, наличие и условия подтверждаются менеджером.\n";
        return ['to'=>implode(', ',$recipients),'subject'=>'=?UTF-8?B?'.base64_encode(($job['kind']==='order'?'Заказ-запрос ':'Обращение ').$row['number']).'?=',
            'headers'=>'From: =?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.">\r\nMessage-ID: ".$job['message_id']."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64",
            'body'=>chunk_split(base64_encode($body),76,"\r\n")];
    }
}
