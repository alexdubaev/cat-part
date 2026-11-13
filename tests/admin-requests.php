<?php
declare(strict_types=1);
require dirname(__DIR__).'/local-dev/admin-runtime/core/admin/config.php';
use Catpart\{Factory,Requests,Outbox};
$pdo=Factory::pdo();
if (!class_exists(Requests::class)) { fwrite(STDERR,"FAIL: checkout has no durable request persistence service\n"); exit(1); }
Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],CATPART_TEST_DATABASE);
if(($argv[1]??'')==='--concurrent'){
    $r=(new Requests($pdo))->submitOrder($argv[2],$argv[3],['name'=>'Concurrent fixture','phone'=>'70000000000','email'=>'concurrent@example.test','consent'=>'1'],[(int)$argv[4]=>1]);echo json_encode(['id'=>$r['id'],'number'=>$r['number']],JSON_THROW_ON_ERROR);exit;
}
Requests::migrate($pdo);$requests=new Requests($pdo);$box=new Outbox($pdo);$checks=0;$ids=[];$leads=[];$keys=[];$captures=[];$extraProducts=[];
$fixture='request-test-'.bin2hex(random_bytes(6));$owner=bin2hex(random_bytes(32));
function check(bool $value,string $label):void {global $checks;if(!$value)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function invalid(callable $fn,string $label):void {try{$fn();}catch(InvalidArgumentException $e){check(true,$label);return;}check(false,$label);}
$q=$pdo->prepare("INSERT INTO products(slug,sku,sku_norm,name,price,currency,in_stock,is_active) VALUES(?,?,?,'Synthetic request fixture',12.50,'RUB',1,1)");$q->execute([$fixture,'000-TEST-REQUEST','000TESTREQUEST']);$product=(int)$pdo->lastInsertId();
$customer=['name'=>str_repeat('Имя',20),'phone'=>'+7 000 000-00-00','email'=>'request-test@example.test','comment'=>'Synthetic only','consent'=>'1'];
$beforeNative=(int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn();$beforeCustomers=(int)$pdo->query('SELECT COUNT(*) FROM oc_customer')->fetchColumn();$nativeStock=hash('sha256',json_encode($pdo->query('SELECT product_id,quantity FROM oc_product ORDER BY product_id')->fetchAll()));
try{
    $token=bin2hex(random_bytes(32));$result=$requests->submitOrder($owner,$token,$customer,[$product=>'150']);$id=$ids[]=$result['id'];$keys[]=$token;
    $order=$requests->order($id);
    check($result['number']==='CP-'.str_pad((string)$id,8,'0',STR_PAD_LEFT),'stable unique request number');
    check((int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn()===$beforeNative+1,'one native OpenCart order');
    check($order['customer']['name']===$customer['name'] && $order['snapshot'][0]['sku']==='000-TEST-REQUEST','full original contact and leading-zero article retained');
    check($order['snapshot'][0]['price']==='12.50' && $order['snapshot'][0]['quantity']===150,'server price and quantity above99 snapshot');
    $native=$pdo->query('SELECT model,quantity,price FROM oc_order_product WHERE order_id='.$id)->fetch();
    check($native['model']==='000-TEST-REQUEST' && (int)$native['quantity']===150 && $native['price']==='12.5000','native order product matches snapshot');
    $again=$requests->submitOrder($owner,$token,[],[]);
    check($again['id']===$id && $again['replayed'] && $again['submitted'][$product]===150,'lost-response replay same order even empty cart');
    check((int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn()===$beforeNative+1,'replay creates no second native order');
    $other=bin2hex(random_bytes(32));check($requests->receipt($other,$token)===null,'receipt inaccessible to another session owner');
    $pdo->exec('UPDATE products SET price=99.00,in_stock=0 WHERE id='.$product);
    check($requests->order($id)['snapshot'][0]['price']==='12.50' && $requests->order($id)['snapshot'][0]['in_stock']===true,'catalog changes do not rewrite original order');
    $manager=(int)$requests->managers()[0]['user_id'];
    $requests->update('order',$id,['status_id'=>103,'manager_id'=>$manager,'note'=>'Internal test','quote_lines'=>[$product=>['price'=>'22.75','currency'=>'RUB']],'quotation'=>'New delivery terms'],$manager);
    $history=$requests->historyRows('order',$id);$quote=json_decode($history[0]['quotation'],true);
    check($requests->order($id)['status_id']===103 && $quote['lines'][0]['price']==='22.75' && $quote['text']==='New delivery terms','manager status assignment structured repricing history');
    check($requests->order($id)['snapshot'][0]['price']==='12.50','repricing does not overwrite original conditions');
    invalid(fn()=>Requests::quantity('1.5'),'fraction quantity rejected');invalid(fn()=>Requests::quantity('1000000'),'quantity maximum explicit');invalid(fn()=>Requests::quantity('-1'),'negative quantity rejected');check(Requests::quantity('999999')===999999,'maximum valid quantity retained');
    $pdo->exec('UPDATE products SET is_active=0 WHERE id='.$product);$failedToken=bin2hex(random_bytes(32));
    invalid(fn()=>$requests->submitOrder($owner,$failedToken,$customer,[$product=>1]),'hidden product blocks entire request');
    check($requests->receipt($owner,$failedToken)===null,'failed transaction leaves no idempotency receipt');
    $pdo->exec('UPDATE products SET is_active=1,price=NULL WHERE id='.$product);$nullToken=bin2hex(random_bytes(32));$nullOrder=$requests->submitOrder($owner,$nullToken,$customer,[$product=>1]);$ids[]=$nullOrder['id'];$keys[]=$nullToken;
    check($requests->order($nullOrder['id'])['snapshot'][0]['price']===null,'on-request remains nullable never invented zero price');
    $pdo->exec('UPDATE products SET price=12.50 WHERE id='.$product);
    $q=$pdo->prepare("INSERT INTO products(slug,sku,sku_norm,name,price,currency,in_stock,is_active) VALUES(?,'000-USD','000USD','Synthetic foreign currency',4.75,'USD',1,1)");$q->execute([$fixture.'-usd']);$foreign=(int)$pdo->lastInsertId();$extraProducts[]=$foreign;
    $mixed=$requests->submitOrder($owner,bin2hex(random_bytes(32)),$customer,[$product=>1,$foreign=>2]);$ids[]=$mixed['id'];
    $nativeMixed=$pdo->query('SELECT total,currency_code FROM oc_order WHERE order_id='.(int)$mixed['id'])->fetch();
    check($nativeMixed['total']==='0.0000' && $nativeMixed['currency_code']==='' && array_column($requests->order($mixed['id'])['snapshot'],'currency')===['RUB','USD'],'mixed currencies preserve individual units without invented FX or sum');
    $leadToken=bin2hex(random_bytes(32));$lead=$requests->submitLead($owner,$leadToken,$customer,'Контакты','/kontakty');$leads[]=$lead['id'];$keys[]=$leadToken;
    $again=$requests->submitLead($owner,$leadToken,[],'','');check($lead['id']===$again['id'] && $requests->lead($lead['id'])['message']==='Synthetic only','all form data persistent lead with idempotency');
    check($requests->listing('lead',['q'=>'request-test@example.test'])['total']>=1,'contact search finds lead');
    $jobIds=[];foreach($ids as $oid)$jobIds[]=(int)$box->forEntity('order',$oid)['id'];foreach($leads as $lid)$jobIds[]=(int)$box->forEntity('lead',$lid)['id'];
    $box->run(static function($job){throw new RuntimeException('transport_error');},100,$jobIds);
    $job=$box->forEntity('order',$id);check($job['state']==='error' && $job['attempts']==1 && $requests->order($id)!==null,'mail failure preserves request and error state');
    $beforeJobs=(int)$pdo->query('SELECT COUNT(*) FROM cp_outbox')->fetchColumn();$box->retry((int)$job['id'],$manager,'order',$id);$box->retry((int)$job['id'],$manager,'order',$id);
    check((int)$pdo->query('SELECT COUNT(*) FROM cp_outbox')->fetchColumn()===$beforeJobs && $box->forEntity('order',$id)['state']==='queued','retry is guarded and reuses job');
    $box->run(static fn($job)=>'captured',100,$jobIds);check($box->forEntity('order',$id)['state']==='captured','retry can complete without changing request');
    check((int)$pdo->query('SELECT COUNT(*) FROM oc_customer')->fetchColumn()===$beforeCustomers,'no customer/subscriber automatically registered');
    check(hash('sha256',json_encode($pdo->query('SELECT product_id,quantity FROM oc_product ORDER BY product_id')->fetchAll()))===$nativeStock,'no native inventory decrement or reservation');
    invalid(fn()=>$requests->update('order',$id,['status_id'=>999],$manager),'unknown processing status rejected');
    $concurrentToken=bin2hex(random_bytes(32));$processes=[];
    for($i=0;$i<2;$i++){
        $pipes=[];$process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'--concurrent',$owner,$concurrentToken,(string)$product],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Concurrent fixture process unavailable');fclose($pipes[0]);$processes[]=[$process,$pipes];
    }
    $results=[];foreach($processes as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException('Concurrent request fixture failed');$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $ids[]=$results[0]['id'];if($results[1]['id']!==$results[0]['id'])$ids[]=$results[1]['id'];
    check($results[0]['id']===$results[1]['id'] && $results[0]['number']===$results[1]['number'],'concurrent processes create one native order and one receipt');
    // A queue write error must roll back only this fixture's native order too.
    $trigger='cp_test_outbox_'.bin2hex(random_bytes(4));$rollbackInput=$customer;$rollbackInput['comment']=$fixture.'-rollback';$quoted=$pdo->quote($rollbackInput['comment']);$pdo->exec("CREATE TRIGGER `$trigger` BEFORE INSERT ON cp_outbox FOR EACH ROW BEGIN IF NEW.kind='order' AND EXISTS(SELECT 1 FROM cp_order WHERE order_id=NEW.entity_id AND JSON_UNQUOTE(JSON_EXTRACT(customer,'$.comment'))=$quoted) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic queue failure'; END IF; END");
    $rollbackToken=bin2hex(random_bytes(32));$count=(int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn();
    try{$requests->submitOrder($owner,$rollbackToken,$rollbackInput,[$product=>1]);check(false,'queue error rejects transaction');}catch(PDOException $e){check((int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn()===$count && $requests->receipt($owner,$rollbackToken)===null,'queue failure rolls back whole native order transaction');}finally{$pdo->exec("DROP TRIGGER `$trigger`");}
    echo "$checks passed, 0 failed\n";
}finally{
    foreach($ids as $id){$pdo->exec("DELETE FROM cp_request_key WHERE kind='order' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_outbox WHERE kind='order' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_request_history WHERE kind='order' AND entity_id=".(int)$id);$pdo->exec('DELETE FROM cp_order WHERE order_id='.(int)$id);$pdo->exec('DELETE FROM oc_order_history WHERE order_id='.(int)$id);$pdo->exec('DELETE FROM oc_order_product WHERE order_id='.(int)$id);$pdo->exec('DELETE FROM oc_order WHERE order_id='.(int)$id);$pdo->exec("DELETE FROM cp_audit WHERE entity='order' AND entity_id='".(int)$id."'");}
    foreach($leads as $id){$pdo->exec("DELETE FROM cp_request_key WHERE kind='lead' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_outbox WHERE kind='lead' AND entity_id=".(int)$id);$pdo->exec("DELETE FROM cp_request_history WHERE kind='lead' AND entity_id=".(int)$id);$pdo->exec('DELETE FROM cp_lead WHERE id='.(int)$id);$pdo->exec("DELETE FROM cp_audit WHERE entity='lead' AND entity_id='".(int)$id."'");}
    foreach($extraProducts as $extra)$pdo->exec('DELETE FROM products WHERE id='.(int)$extra);
    $pdo->exec('DELETE FROM products WHERE id='.$product);
}
