<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{App,Controller,Session,RequestThrottle};
use App\Models\Product;
use Catpart\Requests;
use InvalidArgumentException;
use Throwable;

final class CartController extends Controller
{
    public function index(): void
    {
        $this->owner();
        if (!is_string(Session::get('checkout_token'))) Session::set('checkout_token',bin2hex(random_bytes(32)));
        $this->view('cart/index',[
            'page'=>['title'=>'Корзина | '.App::config('site.name'),'description'=>'Проверьте выбранные запчасти и отправьте запрос на подбор.','canonical'=>'/korzina','breadcrumb'=>[['name'=>'Корзина']]],
            'lines'=>$this->lines(),'ok'=>Session::getFlash('cart_ok'),'error'=>Session::getFlash('cart_error'),
            'old'=>(array)Session::getFlash('cart_old',[]),'fieldErrors'=>(array)Session::getFlash('cart_field_errors',[]),'checkoutToken'=>Session::get('checkout_token'),
        ]);
    }
    public function add(): never
    {
        $this->verifyCsrf();$id=(int)($_POST['product_id']??0);
        if(Product::findActiveById($id)===null)$this->error('Позиция недоступна. Обновите каталог и попробуйте снова.');
        try{$qty=Requests::quantity($_POST['qty']??'1');$cart=$this->cart();$sum=Requests::quantity((string)((int)($cart[(string)$id]??0)+$qty));}
        catch(InvalidArgumentException $e){$this->error($e->getMessage());}
        $cart[(string)$id]=$sum;Session::set('cart',$cart);Session::flash('cart_ok','Позиция добавлена в корзину.');$this->redirect('/korzina');
    }
    public function update(): never
    {
        $this->verifyCsrf();$id=(int)($_POST['product_id']??0);$cart=$this->cart();
        try{$qty=Requests::quantity($_POST['qty']??'',true);}catch(InvalidArgumentException $e){$this->error($e->getMessage());}
        if($qty===0)unset($cart[(string)$id]);elseif(isset($cart[(string)$id]))$cart[(string)$id]=$qty;
        Session::set('cart',$cart);$this->redirect('/korzina');
    }
    public function remove(): never
    {
        $this->verifyCsrf();$cart=$this->cart();unset($cart[(string)((int)($_POST['product_id']??0))]);Session::set('cart',$cart);$this->redirect('/korzina');
    }
    public function checkout(): never
    {
        $this->verifyCsrf();$owner=$this->owner();$token=(string)($_POST['checkout_token']??'');
        $old=[];foreach(['name','phone','email','comment','consent'] as $key)$old[$key]=(string)($_POST[$key]??'');
        if(trim((string)($_POST['website']??''))!=='')$this->error('Не удалось отправить запрос. Проверьте форму и повторите.',$old);
        try{
            $requests=new Requests(App::db()->pdo());$receipt=$requests->receipt($owner,$token);
            if(!$receipt){
                $current=Session::get('checkout_token');
                if(!is_string($current)||!hash_equals($current,$token))throw new InvalidArgumentException('Форма устарела. Проверьте корзину и отправьте новый запрос.');
                Requests::customer($old);
                if(!RequestThrottle::checkoutAllowed())throw new InvalidArgumentException('Слишком много запросов. Попробуйте через 10 минут или свяжитесь с нами по телефону.');
                $submitted=$this->cart();$receipt=$requests->submitOrder($owner,$token,$old,$submitted,'/korzina');
            }
            // A receipt also repairs a lost response after DB commit, exactly once for this session.
            $cleared=(array)Session::get('checkout_cleared',[]);
            if(!isset($cleared[$token])){
                $currentCart=$this->cart();
                foreach(($receipt['submitted']??[]) as $id=>$quantity){$left=(int)($currentCart[$id]??0)-(int)$quantity;if($left>0)$currentCart[$id]=$left;else unset($currentCart[$id]);}
                Session::set('cart',$currentCart);$cleared[$token]=true;Session::set('checkout_cleared',$cleared);
            }
            if(Session::get('checkout_token')===$token)Session::forget('checkout_token');
        }catch(InvalidArgumentException $e){$this->error($e->getMessage(),$old);}
        catch(Throwable $e){error_log('[catpart] request persistence failure: '.get_class($e));$this->error('Не удалось сохранить запрос. Корзина и введённые данные сохранены — повторите отправку этой формы.',$old);}
        Session::flash('cart_ok','Запрос принят. Номер '.$receipt['number'].'. Менеджер подтвердит наличие и стоимость.');$this->redirect('/korzina');
    }
    private function error(string $message,array $old=[]): never {Session::flash('cart_error',$message);Session::flash('cart_field_errors',Requests::fieldErrors($message));if($old)Session::flash('cart_old',$old);$this->redirect('/korzina');}
    private function owner():string {if(!is_string(Session::get('request_owner')))Session::set('request_owner',bin2hex(random_bytes(32)));return Session::get('request_owner');}
    private function cart():array { $cart=Session::get('cart',[]);return is_array($cart)?array_filter($cart,static fn($qty,$id)=>ctype_digit((string)$id)&&(int)$qty>0,ARRAY_FILTER_USE_BOTH):[]; }
    private function lines():array
    {
        $out=[];
        foreach($this->cart() as $id=>$qty){$p=Product::findActiveById((int)$id);$out[]=$p?['id'=>(int)$id,'sku'=>$p['sku'],'name'=>$p['name'],'price'=>$p['price'],'currency'=>$p['currency']??'RUB','qty'=>(int)$qty,'available'=>true]:['id'=>(int)$id,'sku'=>'№'.$id,'name'=>'Позиция недоступна. Удалите её или уточните у менеджера.','price'=>null,'currency'=>'RUB','qty'=>(int)$qty,'available'=>false];}
        return $out;
    }
}
