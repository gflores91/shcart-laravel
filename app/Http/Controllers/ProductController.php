<?php

namespace shcart\Http\Controllers;

use Illuminate\Http\Request;

//Librerias
use Session;
use Redirect;
use Stripe\Stripe;
use Stripe\Charge;
use Auth;

//Models
use shcart\Cart;
use shcart\Product;
use shcart\Order;

// Valor USD
use Exchanger\Service\Service;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\ExchangeRate;
use Swap\Service\Registry;
use Swap\Builder;
use Swap;

class ProductController extends Controller
{
    //Page: Inicio
    //route: product.index
    //params:
    //Models: shcart\Product
    //return: $products -> views/shop/index
    public function index()
    {
        $products = Product::all();

        return view('shop.index',['products' => $products]);
    }

    public function carro()
    {
        if(!Session::has('cart'))
            return view('shop.carrodecompras', ['products' => null]);
        
        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);
        return view('shop.carrodecompras', [
            'products' => $cart->items,
            'totalPrecio' => $cart->totalPrecio 
            ]);
    }

    public function anadiralcarro(Request $request, $id)
    {
        $product = Product::find($id);
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->add($product, $product->id);
        
        $request->session()->put('cart', $cart);
        
        return Redirect::route('product.index');
        
    }

    public function removerunitemcarro($id)
    {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->removeaitem($id);

        Session::put('cart', $cart);
        return Redirect::route('product.carro');
    }

    public function removeritemcarro($id)
    {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->removeallitem($id);

        if(count($cart->items) > 0)
        {
            Session::put('cart', $cart);
        }
        else
        {
            Session::forget('cart');
        }

        Session::put('cart', $cart);
        return Redirect::route('product.carro');
    }

    public function comprar()
    {
        if(!Session::has('cart'))
        {
            return view('shop.carrodecompras');
        }

        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);
        $total = $cart->totalPrecio;
        
        return view('shop.comprar', [
            'total' => $total
        ]);
    }

    public function postcomprar(Request $request)
    {
        if(!Session::has('cart'))
        {
            return Redirect::route('product.carro');
        }

        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);

        Stripe::setApiKey('coloca aqui tu private key');

        try
        {
            $rate = Swap::latest('USD/CLP');
            $charge = Charge::create(array(
                "amount" => (number_format(($cart->totalPrecio / $rate->getValue()),2) * 100),
                "currency" => "usd",
                "description" => "Example charge",
                "source" => $request->input('stripeToken'),
                ));
            
            $order = Order::create([
                        'user_id' => Auth::user()->id,
                        'carro' => serialize($cart),
                        'nombres' => $request->input('nombres'),
                        'apellidos' => $request->input('apellidos'),
                        'email' => $request->input('email'),
                        'direccion' => $request->input('direccion'),
                        'codigo_postal' => $request->input('codigo_postal'),
                        'telefono' => $request->input('telefono'),
                        'movil' => $request->input('movil'),
                        'comentario' => $request->input('comentario'),
                        'id_pago' => $charge->id
                     ]);
            Auth::user()->orders()->save($order);
        }
        catch(Exception $e)
        {
            return Redirect::route('product.comprar')->with('error' , $e->getMessage());
        }



        Session::forget('cart');
        return Redirect::route('product.index')->with('success' , 'Compra realizada con exito');
    }
}