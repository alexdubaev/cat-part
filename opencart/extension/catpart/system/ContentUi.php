<?php
declare(strict_types=1);
namespace Catpart;
final class ContentUi
{
    private static string $error='';
    public static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function field(string $name,string $label,mixed $value='',string $type='text',string $help=''):string
    {
        $n=self::e($name);$v=self::e($value);$id='cp-'.preg_replace('/[^a-z0-9_-]/i','-',$name);
        $aliases=['email'=>'Email','phones'=>'Телефон','primary_phone'=>'Основной телефон','published_at'=>'Дата','slug'=>'Адрес','title'=>'Заголовок','map_widget'=>'Карта','ga4_id'=>'Google Analytics','metrika_id'=>'Яндекс Метрика','canonical'=>'Canonical','from_email'=>'Почта','notify'=>'Получател'];
        $needle=$aliases[$name]??$label;$invalid=self::$error!==''&&mb_stripos(self::$error,$needle)!==false;
        $control=in_array($type,['textarea','rich'],true)?'<textarea class="form-control '.($type==='rich'?'cp-rich':'').'" id="'.$id.'" name="'.$n.'" rows="'.($type==='rich'?14:3).'">'.$v.'</textarea>':'<input class="form-control" id="'.$id.'" name="'.$n.'" type="'.self::e($type).'" value="'.$v.'">';
        if($invalid)$control=str_replace('class="form-control','aria-invalid="true" aria-describedby="'.$id.'-error" class="form-control is-invalid',$control);
        return '<div class="mb-3"><label class="form-label" for="'.$id.'">'.self::e($label).'</label>'.$control.($invalid?'<div class="invalid-feedback d-block" id="'.$id.'-error">'.self::e(self::$error).'</div>':'').($help!==''?'<div class="form-text">'.self::e($help).'</div>':'').'</div>';
    }
    public static function select(string $name,string $label,array $choices,mixed $value):string
    {
        $html='<div class="mb-3"><label class="form-label">'.self::e($label).'<select name="'.self::e($name).'" class="form-select">';foreach($choices as $key=>$text)$html.='<option value="'.self::e($key).'"'.((string)$key===(string)$value?' selected':'').'>'.self::e($text).'</option>';return $html.'</select></label></div>';
    }
    public static function check(string $name,string $label,bool $checked):string{return '<label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="'.self::e($name).'" value="1"'.($checked?' checked':'').'> '.self::e($label).'</label>';}
    public static function start(string $action,string $error='',string $success=''):string
    {
        self::$error=$error;
        return ($error!==''?'<div class="alert alert-danger" role="alert">'.self::e($error).'</div>':'').($success!==''?'<div class="alert alert-success" role="status">'.self::e($success).'</div>':'').'<form method="post" action="'.self::e($action).'" enctype="multipart/form-data" data-cp-form>';
    }
    public static function buttons(string $back=''):string{return '<div class="my-3 d-flex gap-2"><button type="submit" class="btn btn-primary">Сохранить</button>'.($back!==''?'<a class="btn btn-light" href="'.self::e($back).'">К списку</a>':'').'</div></form>';}
    public static function scripts(string $upload):string
    {
        return '<div class="card mb-3"><div class="card-body"><label>Новое изображение (JPG, PNG, WebP, GIF; до10МБ)<input type="file" class="form-control" id="cp-image-upload" accept="image/jpeg,image/png,image/webp,image/gif" data-url="'.self::e($upload).'"></label><div id="cp-upload-result" role="status"></div></div></div><script src="/admin/view/javascript/ckeditor/ckeditor.js"></script><script src="/extension/catpart/admin/view/assets/content.js"></script>';
    }
}
