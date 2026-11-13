<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
use Catpart\CatalogImport;

final class Imports extends \Catpart\AdminController
{
    public function index():void{if(!$this->guard('imports'))return;$this->screen();}
    public function upload():void
    {
        if(!$this->guard('imports',true))return;try{$job=$this->service()->upload($this->request->files['catalog_file']??[],(int)$this->user->getId());$this->redirect($this->url('imports','',['id'=>$job['id']]));}catch(\InvalidArgumentException $e){$this->screen($e->getMessage());}
    }
    public function preview():void
    {
        if(!$this->guard('imports',true))return;try{$job=$this->service()->preview((string)($this->request->post['id']??''),(array)($this->request->post['mapping']??[]),(int)$this->user->getId(),!empty($this->request->post['resume']));$this->redirect($this->url('imports','',['id'=>$job['id']]));}catch(\InvalidArgumentException $e){$this->request->get['id']=$this->request->post['id']??'';$this->screen($e->getMessage());}
    }
    public function apply():void
    {
        if(!$this->guard('imports',true))return;try{$job=$this->service()->apply((string)($this->request->post['id']??''),(int)$this->user->getId(),!empty($this->request->post['confirmed']));$this->redirect($this->url('imports','',['id'=>$job['id']]));}catch(\InvalidArgumentException $e){$this->request->get['id']=$this->request->post['id']??'';$this->screen($e->getMessage());}
    }
    public function download():void
    {
        if(!$this->guard('imports'))return;try{$job=$this->service()->job((string)($this->request->get['id']??''),(int)$this->user->getId());$this->response->addHeader('Content-Type: application/octet-stream');$this->response->addHeader('Content-Disposition: attachment; filename="catalog-source.'.strtolower(pathinfo($job['filename'],PATHINFO_EXTENSION)).'"');$this->response->addHeader('X-Content-Type-Options: nosniff');$this->response->setOutput((string)file_get_contents($job['file_path']));}catch(\InvalidArgumentException){$this->response->addHeader('HTTP/1.1 404 Not Found');$this->response->setOutput('Файл недоступен.');}
    }
    private function screen(string $error=''):void
    {
        $s=$this->service();$actor=(int)$this->user->getId();$limit=number_format(CatalogImport::uploadLimit()/1048576,2,',','');$h='<p>CSV или XLSX: текущий лимит загрузки '.$limit.' МиБ (минимум лимита импорта 16 МиБ и действующих настроек PHP), до 100 000 строк и 200 колонок. Книга XLSX: распакованный объём до 64 МиБ, словарь строк до 8 МиБ. Это защита памяти PHP 128 МиБ; исходный каталог содержит 7 134 товара. Обработка выполняется шагами до 250 строк / 10 секунд, применение до 100 строк за шаг. Данные не обрезаются. При необходимости продолжите следующий шаг.</p><p>Используется первая вкладка книги. Артикулы в Excel храните как текст: уже потерянные ведущие нули восстановить нельзя. Формулы не принимаются; сохраните значения.</p>';
        if($error!=='')$h.='<div class="alert alert-danger" role="alert">'.self::esc($error).'</div>';
        $h.='<form method="post" enctype="multipart/form-data" action="'.self::esc($this->url('imports','upload')).'"><label class="form-label">Файл каталога<input class="form-control" type="file" required name="catalog_file" accept=".csv,.xlsx"></label><p><button class="btn btn-primary">Загрузить и выбрать колонки</button></p></form>';
        $id=(string)($this->request->get['id']??'');
        if($id!=='')try{
            $job=$s->job($id,$actor);$h.='<hr><h2>'.self::esc($job['filename']).'</h2><p>Состояние: '.self::esc(['uploaded'=>'Ожидает сопоставления','previewing'=>'Проверка — требуется продолжение','previewed'=>'Предварительная проверка завершена','applying'=>'Применение — требуется продолжение','done'=>'Обработка завершена'][$job['status']]??$job['status']).'</p><p><a href="'.self::esc($this->url('imports','download',['id'=>$id])).'">Скачать свой исходный файл</a></p>';
            if(in_array($job['status'],['uploaded','previewed'],true)){
                $h.='<form method="post" action="'.self::esc($this->url('imports','preview')).'"><input type="hidden" name="id" value="'.$id.'"><h3>Сопоставление колонок</h3><p>Не выбранные поля существующего товара сохраняются. Пустая сопоставленная ячейка очищает необязательное поле. Новые товары без явного статуса остаются черновиками.</p>';
                foreach($job['headers'] as $column=>$label){$selected=$job['mapping'][$column]??'';$h.='<div class="mb-2"><label class="form-label">'.self::esc($label).' (колонка '.($column+1).')<select class="form-select" name="mapping['.$column.']"><option value="">Не импортировать</option>';foreach(CatalogImport::FIELDS as $field=>$name)$h.='<option value="'.$field.'" '.($selected===$field?'selected':'').'>'.self::esc($name).'</option>';$h.='</select></label></div>';}$h.='<button class="btn btn-secondary">Проверить без изменения каталога</button></form>';
            }
            if($job['status']==='previewing')$h.=$this->action('preview',$id,'Продолжить проверку','<input type="hidden" name="resume" value="1">');
            $h.='<p class="my-3">Готово к применению: '.(int)$job['summary']['ready'].'; применено: '.(int)$job['summary']['done'].'; ошибок: '.(int)$job['summary']['error'].'.</p>';
            if(in_array($job['status'],['previewed','applying'],true) && ($job['summary']['error']===0||$job['status']==='applying'))$h.=$this->action('apply',$id,$job['status']==='applying'?'Продолжить применение':'Применить проверенные изменения','<label><input type="checkbox" required name="confirmed" value="1"> Подтверждаю создание и обновление показанных товаров, включая указанные смены адресов и перенаправления 301</label><br>');
            $page=max(1,(int)($this->request->get['page']??1));$rows=$s->report($id,$actor,$page);$h.='<div class="table-responsive"><table class="table"><thead><tr><th>Строка</th><th>Товар</th><th>Действие</th><th>Результат / причина</th></tr></thead><tbody>';foreach($rows as $row){$input=json_decode($row['input_data'],true);$h.='<tr><td>'.$row['line_number'].'</td><td>'.self::esc($input['sku']??$input['id']??'').' '.self::esc($input['name']??'').'</td><td>'.self::esc(['create'=>'Создать','update'=>'Обновить','error'=>'Ошибка'][$row['action']]??$row['action']).'</td><td>'.self::esc($row['message']!==''?$row['message']:['ready'=>'Проверено','done'=>'Сохранено','error'=>'Ошибка'][$row['status']]).'</td></tr>';}$h.='</tbody></table></div>';if($page>1)$h.='<a class="btn btn-light" href="'.self::esc($this->url('imports','',['id'=>$id,'page'=>$page-1])).'">← Назад</a> ';if(count($rows)===100)$h.='<a class="btn btn-light" href="'.self::esc($this->url('imports','',['id'=>$id,'page'=>$page+1])).'">Следующие строки →</a>';
        }catch(\InvalidArgumentException $e){$h.='<div class="alert alert-danger">'.self::esc($e->getMessage()).'</div>';}
        $h.='<hr><h2>Мои импорты</h2><ul>';foreach($s->recent($actor) as $job)$h.='<li><a href="'.self::esc($this->url('imports','',['id'=>$job['id']])).'">'.self::esc($job['filename']).'</a> — '.self::esc($job['created_at']).'</li>';$h.='</ul>';$this->page('Импорт каталога',$h);
    }
    private function action(string $action,string $id,string $label,string $extra):string{return '<form class="my-3" method="post" action="'.self::esc($this->url('imports',$action)).'"><input type="hidden" name="id" value="'.$id.'">'.$extra.'<button class="btn btn-primary">'.$label.'</button></form>';}
    private function service():CatalogImport{return new CatalogImport($this->pdo());}
}
