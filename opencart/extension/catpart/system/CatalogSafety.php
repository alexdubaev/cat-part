<?php
declare(strict_types=1);
namespace Catpart;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/** DOM allowlist adapted from CMTEH CatalogInput (8a5ada0), for CatPart media paths. */
final class CatalogSafety
{
    public static function text(mixed $value, int $max, string $label, bool $required=false): string
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException($label.': требуется текст.');
        $s=trim((string)$value);
        if (!mb_check_encoding($s,'UTF-8') || mb_strlen($s)>$max || ($required && $s==='') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$s)) throw new InvalidArgumentException($label.': недопустимое или слишком длинное значение.');
        return $s;
    }
    public static function url(string $value): string
    {
        $s=self::text($value,1000,'Ссылка');
        if ($s==='') return '';
        if (preg_match('/[\x00-\x20\\\\]/',$s) || str_starts_with($s,'//')) throw new InvalidArgumentException('Ссылка: небезопасный адрес.');
        if ($s[0]==='/' || $s[0]==='#') {
            if (preg_match('~(?:^|/)\.\.(?:/|$)~',rawurldecode($s))) throw new InvalidArgumentException('Ссылка: запрещён переход между каталогами.');
            return $s;
        }
        $parts=parse_url($s);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || !in_array(strtolower($parts['scheme']??''),['https','http','mailto','tel'],true)) throw new InvalidArgumentException('Ссылка: допустимы HTTPS, HTTP, email, телефон и локальный путь.');
        if (in_array(strtolower($parts['scheme']),['http','https'],true) && empty($parts['host'])) throw new InvalidArgumentException('Ссылка: не указан сайт.');
        return $s;
    }
    public static function image(string $value): string
    {
        try{$s=self::url($value);}catch(InvalidArgumentException){throw new InvalidArgumentException('Изображение: небезопасный адрес.');}
        if ($s==='') return '';
        if (!preg_match('~^/(?:upload|assets|media)/[A-Za-z0-9_./%\p{L}\p{N}-]+\.(?:png|jpe?g|webp|gif|ico)$~iuD',$s) || str_contains(rawurldecode($s),'..')) throw new InvalidArgumentException('Изображение: выберите PNG, JPEG, WebP или GIF из файлов CatPart.');
        return $s;
    }
    public static function html(string $value): string
    {
        if (strlen($value)>2*1024*1024 || !mb_check_encoding($value,'UTF-8')) throw new InvalidArgumentException('Содержание: допустимо до 2 МиБ текста UTF-8.');
        $d=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        try { $d->loadHTML('<?xml encoding="UTF-8"><div id="cp-sanitizer-root">'.$value.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING); }
        finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        $root=$d->getElementById('cp-sanitizer-root');if (!$root) return '';
        self::clean($root);$out='';foreach($root->childNodes as $child)$out.=$d->saveHTML($child);return $out;
    }
    private static function clean(DOMNode $node): void
    {
        $allowed=['p','h2','h3','h4','h5','h6','ul','ol','li','a','blockquote','table','thead','tbody','tfoot','tr','th','td','strong','b','em','i','u','s','br','hr','img','figure','figcaption','div','span','sup','sub'];
        foreach(iterator_to_array($node->childNodes) as $child){
            if ($child->nodeType===XML_COMMENT_NODE || $child->nodeType===XML_PI_NODE){$node->removeChild($child);continue;}
            if (!$child instanceof DOMElement) continue;
            $tag=strtolower($child->tagName);
            if (in_array($tag,['script','style','iframe','object','embed','form','input','button','textarea','select','link','meta','svg','math'],true)){$node->removeChild($child);continue;}
            self::clean($child);
            if (!in_array($tag,$allowed,true)){while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;}
            foreach(iterator_to_array($child->attributes) as $a){
                $name=strtolower($a->name);$value=$a->value;$keep=false;
                if ($tag==='a' && $name==='href'){try{$child->setAttribute('href',self::url($value));$keep=true;}catch(InvalidArgumentException){}}
                elseif($tag==='img' && $name==='src'){try{$child->setAttribute('src',self::image($value));$keep=true;}catch(InvalidArgumentException){}}
                elseif(in_array($name,['title','alt'],true)){$child->setAttribute($name,mb_substr($value,0,255));$keep=true;}
                elseif(in_array($name,['colspan','rowspan','width','height'],true) && ctype_digit($value) && (int)$value>0 && (int)$value<=($name==='colspan'||$name==='rowspan'?30:4000))$keep=true;
                if (!$keep)$child->removeAttribute($a->name);
            }
            if ($tag==='a')$child->setAttribute('rel','noopener noreferrer');
            if($tag==='img' && !$child->hasAttribute('src'))$node->removeChild($child);
        }
    }
}
