<?php
require dirname(__DIR__).'/opencart/extension/catpart/system/autoload.php';
$cases=[
 [['host'=>'localhost','port'=>3307,'name'=>'local_catpart'],null,false],
 [['host'=>'db.example.invalid','port'=>3307,'name'=>'catpart_admin_0123456789ab'],'catpart_admin_0123456789ab',false],
 [['host'=>'127.0.0.1','port'=>3306,'name'=>'catpart_admin_0123456789ab'],'catpart_admin_0123456789ab',false],
 [['host'=>'127.0.0.1','port'=>3307,'name'=>'local_catpart'],'catpart_admin_0123456789ab',false],
 [['host'=>'127.0.0.1','port'=>3307,'name'=>'catpart_admin_111111111111'],'catpart_admin_0123456789ab',false],
 [['host'=>'127.0.0.1','port'=>3307,'name'=>'catpart_admin_0123456789ab'],'catpart_admin_0123456789ab',true],
 [['host'=>'127.0.0.1','port'=>3307,'name'=>'local_catpart'],null,true],
];
foreach($cases as $i=>[$cfg,$expected,$allowed]){
 $result=true;try{\Catpart\Factory::assertLocal($cfg,$expected);}catch(RuntimeException){$result=false;}
 if($result!==$allowed)throw new RuntimeException('guard case '.($i+1).' failed');
}
echo count($cases)." guard cases passed\n";
