<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\App;
final class Post
{
    private const FROM=' FROM blog_posts p LEFT JOIN cp_article a ON a.post_id=p.id';
    private const VISIBLE="p.is_active=1 AND p.published_at<=UTC_TIMESTAMP() AND COALESCE(a.status,'published') IN ('published','scheduled')";
    private const COLUMNS="p.*,COALESCE(a.status,'published') status,COALESCE(a.author,'') author,COALESCE(a.seo_h1,'') seo_h1,COALESCE(a.canonical,'') canonical,COALESCE(a.noindex,0) noindex,COALESCE(a.og_image,'') og_image,COALESCE(a.cover_alt,'') cover_alt,COALESCE(a.related_products,'[]') related_products";
    public static function latest(int $limit=20):array{return App::db()->fetchAll('SELECT '.self::COLUMNS.self::FROM.' WHERE '.self::VISIBLE.' ORDER BY p.published_at DESC,p.id DESC LIMIT :lim',['lim'=>max(1,min(50,$limit))]);}
    public static function findBySlug(string $slug):?array{return App::db()->fetch('SELECT '.self::COLUMNS.self::FROM.' WHERE p.slug=:slug AND '.self::VISIBLE.' LIMIT 1',['slug'=>$slug]);}
    public static function others(int $exceptId,int $limit=3):array
    {
        $row=App::db()->fetch('SELECT related_articles FROM cp_article WHERE post_id=:id',['id'=>$exceptId]);$ids=json_decode((string)($row['related_articles']??'[]'),true);
        if(is_array($ids)&&$ids!==[]){$ids=array_values(array_filter(array_map('intval',$ids),static fn($id)=>$id>0&&$id!==$exceptId));if($ids!==[])return App::db()->fetchAll('SELECT '.self::COLUMNS.self::FROM.' WHERE p.id IN ('.implode(',',$ids).') AND '.self::VISIBLE.' ORDER BY FIELD(p.id,'.implode(',',$ids).') LIMIT :lim',['lim'=>max(1,min(12,$limit))]);}
        return App::db()->fetchAll('SELECT '.self::COLUMNS.self::FROM.' WHERE p.id<>:id AND '.self::VISIBLE.' ORDER BY p.published_at DESC,p.id DESC LIMIT :lim',['id'=>$exceptId,'lim'=>max(1,min(12,$limit))]);
    }
    public static function allPublished():array{return App::db()->fetchAll('SELECT p.slug,p.published_at'.self::FROM.' WHERE '.self::VISIBLE.' AND COALESCE(a.noindex,0)=0 ORDER BY p.published_at DESC,p.id DESC');}
}
