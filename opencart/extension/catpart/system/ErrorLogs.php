<?php
declare(strict_types=1);
namespace Catpart;
/** Read-only, bounded view of fixed server-owned error logs; no request paths. */
final class ErrorLogs
{
    public static function listing():array
    {
        $runtime=getenv('CATPART_ADMIN_RUNTIME');
        $sources=['OpenCart'=>defined('DIR_LOGS')?DIR_LOGS.'error.log':null,
            'PHP'=>is_string($runtime)&&$runtime!==''?rtrim($runtime,'/\\').'/private/php-errors.log':null];
        $out=[];
        foreach($sources as $label=>$path){
            if(!$path || !is_file($path) || !is_readable($path)){$out[$label]=['status'=>'Журнал недоступен или ещё не создан.','text'=>''];continue;}
            $handle=@fopen($path,'rb');if(!$handle){$out[$label]=['status'=>'Журнал недоступен.','text'=>''];continue;}
            try{$stat=fstat($handle);$offset=max(0,(int)$stat['size']-65536);fseek($handle,$offset);$text=(string)stream_get_contents($handle,65536);if($offset>0){$newline=strpos($text,"\n");$text=$newline===false?'':substr($text,$newline+1);}}
            finally{fclose($handle);}
            $lines=preg_split('/\r?\n/',$text)?:[];$text=implode("\n",array_slice($lines,-200));
            $out[$label]=['status'=>$text===''?'Журнал пуст.':'Последние до 200 строк / 64 КиБ.','text'=>self::redact($text)];
        }
        return $out;
    }
    private static function redact(string $text):string
    {
        // Drop entire credential-bearing fragments, including quoted values with spaces.
        $text=preg_replace('~\b(Authorization|Proxy-Authorization|Cookie|Set-Cookie)\s*[:=]\s*[^\r\n]*~i','$1: [скрыто]',$text)??'';
        $text=preg_replace('~((?:["\']?)(?:authorization|proxy-authorization|username|email|user_token|access_token|refresh_token|token|password|passwd|pwd|secret|api[_-]?key|cookie|set-cookie|session(?:_id)?|PHPSESSID)(?:["\']?)\s*(?:=>|=|:|%3D)\s*)(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s&,;<>]+)~i','$1[скрыто]',$text)??'';
        $text=preg_replace('~(https?://)[^\s/@]+:[^\s/@]+@~i','$1[скрыто]@',$text)??'';
        $text=preg_replace('~(for user)\\s+([\"\'][^\"\']*[\"\'])~i','$1 [скрыто]',$text)??'';
        return $text;
    }
}
