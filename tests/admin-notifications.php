<?php
declare(strict_types=1);
// Synthetic fixtures and injected sender only; this test never sends mail.
$runtime=getenv('CATPART_ADMIN_RUNTIME');
if(!is_string($runtime) || !is_file($runtime.'/state.json'))throw new RuntimeException('Explicit isolated runtime required.');
require $runtime.'/core/admin/config.php';
if(!defined('CATPART_TEST_DATABASE'))throw new RuntimeException('Isolated test database required.');
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],CATPART_TEST_DATABASE);
$pdo=\Catpart\Factory::pdo();$box=new \Catpart\Outbox($pdo);$checks=0;$lead=0;$captureFile=null;
function notificationCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function notificationFailure(callable $call,string $code,string $label):void {try{$call();}catch(RuntimeException $e){notificationCheck($e->getMessage()===$code,$label);return;}throw new RuntimeException($label);}
$saved=$pdo->query("SELECT section,data,updated_at FROM cp_settings WHERE section IN ('mail','site')")->fetchAll();
$put=$pdo->prepare('INSERT INTO cp_settings(section,data,updated_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),updated_at=VALUES(updated_at)');
$tag='notify-'.bin2hex(random_bytes(6));$calls=[];
try {
    $put->execute(['mail',json_encode(['notify'=>['manager@example.test',"bad@example.test\r\nBcc: evil@example.test"],'from_email'=>'sender@example.test','from_name'=>"CatPart\r\nBcc: injected@example.test"],JSON_THROW_ON_ERROR)]);
    $put->execute(['site',json_encode(['email'=>'site@example.test','company'=>'CatPart'],JSON_THROW_ON_ERROR)]);
    $q=$pdo->prepare("INSERT INTO cp_lead(number,type,source,name,phone,email,message,status_id,created_at,updated_at) VALUES(?,'Synthetic notification','/test','Synthetic fixture','70000000000','fixture@example.test',?,101,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $comment="Synthetic body\r\nBcc: customer-injection@example.test";$q->execute([$tag,$comment]);$lead=(int)$pdo->lastInsertId();$box->enqueue('lead',$lead);$job=$box->forEntity('lead',$lead);$jobId=(int)$job['id'];
    $transport=$box->mailTransport([],[],static function($to,$subject,$body,$headers)use(&$calls):bool {$calls[]=compact('to','subject','body','headers');return false;});
    $result=$box->run($transport,1,[$jobId]);$failed=$box->forEntity('lead',$lead);
    notificationCheck($result['errors']===1 && $failed['state']==='error' && (int)$failed['attempts']===1 && $failed['error_code']==='transport_error' && $pdo->query('SELECT COUNT(*) FROM cp_lead WHERE id='.$lead)->fetchColumn()==1,'sender rejection keeps the request and records safe retryable failure');
    $sent=$calls[0];
    notificationCheck($sent['to']==='manager@example.test' && !str_contains($sent['headers'],"\r\nBcc:") && !str_contains($sent['headers'],'customer-injection') && !str_contains($sent['subject'],"\r"),'headers contain validated recipients and encoded names, never customer input');
    notificationCheck(str_contains((string)base64_decode($sent['body'],true),$comment) && str_contains($sent['headers'],'Message-ID: '.$job['message_id']) && str_contains($sent['headers'],'Content-Transfer-Encoding: base64'),'body retains original message and stable message ID');
    $box->retry($jobId,987654319,'lead',$lead);
    $accepted=$box->mailTransport([],[],static function($to,$subject,$body,$headers)use(&$calls):bool {$calls[]=compact('to','subject','body','headers');return true;});
    $result=$box->run($accepted,1,[$jobId]);
    notificationCheck($result['errors']===0 && $box->forEntity('lead',$lead)['state']==='accepted' && $calls[0]===$calls[1],'MTA acceptance completes the retried job with identical content');
    $box->run($accepted,1,[$jobId]);notificationCheck(count($calls)===2,'accepted job is not sent again');
    $forged=$job;$forged['message_id'].="\r\nBcc: evil@example.test";
    notificationFailure(static fn()=>$accepted($forged),'transport_error','stored message ID cannot inject a header');
    $put->execute(['mail',json_encode(['notify'=>[],'from_email'=>'sender@example.test'],JSON_THROW_ON_ERROR)]);
    notificationFailure(static fn()=>$accepted($job),'no_recipient','empty configured recipient list does not silently fall back');
    $put->execute(['mail',json_encode(['notify'=>['manager@example.test'],'from_email'=>"bad@example.test\r\nBcc: evil@example.test"],JSON_THROW_ON_ERROR)]);
    notificationFailure(static fn()=>$accepted($job),'invalid_sender','sender header injection rejected');
    $pdo->exec("DELETE FROM cp_settings WHERE section IN ('mail','site')");$fallback=[];
    $defaults=$box->mailTransport(['notify'=>['fallback@example.test'],'from_email'=>'fallback-sender@example.test'],[],static function($to,$subject,$body,$headers)use(&$fallback):bool {$fallback=compact('to','headers');return true;});
    notificationCheck($defaults($job)==='accepted' && $fallback['to']==='fallback@example.test' && str_contains($fallback['headers'],'fallback-sender@example.test'),'legacy application mail defaults work before DB mail settings exist');
    $put->execute(['mail',json_encode(['notify'=>['manager@example.test'],'from_email'=>'sender@example.test','from_name'=>'CatPart'],JSON_THROW_ON_ERROR)]);
    $directory=$runtime.'/private/notification-test-'.$tag;$capture=$box->captureTransport($directory,dirname(__DIR__).'/local-dev/site');
    notificationCheck($capture($job)==='captured' && $capture($job)==='captured','capture transport remains deterministic and idempotent');
    $captureFile=$directory.'/'.hash('sha256',$job['message_id']).'.eml';$mime=(string)file_get_contents($captureFile);
    notificationCheck(str_starts_with($mime,"To: manager@example.test\r\nFrom: ") && str_contains($mime,"\r\nSubject: =?UTF-8?B?"),'capture keeps existing MIME header order');
    echo $checks." notification checks passed; no mail sent\n";
} finally {
    $pdo->exec("DELETE FROM cp_settings WHERE section IN ('mail','site')");$restore=$pdo->prepare('INSERT INTO cp_settings(section,data,updated_at) VALUES(?,?,?)');foreach($saved as $row)$restore->execute([$row['section'],$row['data'],$row['updated_at']]);
    if($lead){$pdo->exec("DELETE FROM cp_outbox WHERE kind='lead' AND entity_id=".$lead);$pdo->exec("DELETE FROM cp_audit WHERE actor_id=987654319 AND entity='lead' AND entity_id=".$pdo->quote((string)$lead));$pdo->exec('DELETE FROM cp_lead WHERE id='.$lead);}
    if($captureFile && is_file($captureFile)){unlink($captureFile);rmdir(dirname($captureFile));}
}
