<?php
declare(strict_types=1);
$runtime=getenv('CATPART_ADMIN_RUNTIME')?:dirname(__DIR__).'/local-dev/admin-runtime';
require $runtime.'/core/admin/config.php';
if(!defined('CATPART_TEST_DATABASE') || !preg_match('/^catpart_admin_[a-f0-9]{12}$/D',DB_DATABASE))throw new RuntimeException('Isolated fixture database required');
$input=json_decode((string)stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$id=(int)($input['id']??0);$slug=(string)($input['slug']??'');
if($id<1 || !preg_match('/^content-browser-[0-9]+$/D',$slug))throw new RuntimeException('Exact browser fixture identity required');
$pdo=Catpart\Factory::pdo();$pdo->beginTransaction();try{
 $query=$pdo->prepare('SELECT id FROM blog_posts WHERE id=? AND slug=? AND title=? FOR UPDATE');$query->execute([$id,$slug,'Тест редактора & CatPart']);if(!$query->fetchColumn())throw new RuntimeException('Browser fixture identity does not match; nothing removed');
 foreach(['cp_article_revision'=>'post_id','cp_article'=>'post_id','blog_posts'=>'id'] as $table=>$key){$query=$pdo->prepare('DELETE FROM '.$table.' WHERE '.$key.'=?');$query->execute([$id]);}
 $pdo->commit();echo "Exact content browser fixture removed\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
