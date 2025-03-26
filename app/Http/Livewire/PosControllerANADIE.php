<?php

namespace App\Http\Livewire;

use App\Models\Sale;
use App\Models\Product;
use Livewire\Component;
use App\Models\Customer;
use App\Models\SaleDetail;
use App\Models\Denomination;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Traits\JsonTrait;

class PosController extends Component
{
    use JsonTrait;

    public $posCart;
    public $totalCart = 0;
    public $totalItems = 0;
    public $total, $itemsQuantity, $cash, $change, $customers = [],
        $customerId, $customerName, $status, $payment_type, $payment_method, $discount, $discount_total;

    protected $listeners = [
        'scan-code' => 'addProduct',
        'removeItem' => 'removeItem',
        'clearCart' => 'clearCart',
        'saveSale' => 'saveSale',
        'refresh' => '$refresh',
        'scan-code-byid' => 'scanCodeById',
        'setCustomer',
        'print-json'
    ];

    public function mount()
    {
        $this->initializeCart();
        $this->initializeDefaults();
    }

    public function render()
    {
        $this->updateCartMetrics();
        $this->posCart = $this->posCart->sortBy('name');

        return view('livewire.pos.component', [
            'denominations' => Denomination::get(),
            'posCart' => $this->posCart
        ])->extends('layouts.theme.app')->section('content');
    }

    public function initializeCart()
    {
        $this->posCart = session()->has('posCart') ? collect(session('posCart')) : collect([]);
    }

    public function initializeDefaults()
    {
        $this->cash = 0;
        $this->change = 0;
        $this->totalCart = 0;
        $this->totalItems = 0;
        $this->status = 'PAGADO';
        $this->payment_type = 'CONTADO';
        $this->payment_method = 'EFECTIVO';
        $this->discount = 0;
        $this->discount_total = 0;
        $this->assignOccasionalCustomer();
    }

    public function updateCartMetrics()
    {
        $this->totalItems = $this->posCart->sum('quantity');
        $this->itemsQuantity = $this->totalItems; // Sincronización para usarlo en la vista
        $this->totalCart = $this->totalCart();
    }

    public function assignOccasionalCustomer()
    {
        $occasionalCustomerId = 1;
        $occasionalCustomer = Customer::find($occasionalCustomerId);

        if ($occasionalCustomer) {
            $this->customerId = $occasionalCustomer->id;
            $this->customerName = $occasionalCustomer->name;
        } else {
            $this->customerId = null;
            $this->customerName = "Cliente no definido";
            $this->emit('error', 'Cliente ocasional no encontrado en la base de datos');
        }
    }

    public function addProduct($barcode, $quantity = 1)
    {
        if ($this->isWeightedProductBarcode($barcode)) {
            $this->addWeightProduct($barcode);
        } else {
            $this->addUnitProduct($barcode, $quantity);
        }
    }

    public function addUnitProduct($barcode, $quantity = 1)
    {
        $product = Product::where('barcode', $barcode)->first();

        if (!$product) {
            $this->emit('scan-notfound', 'El producto no está registrado');
            return;
        }

        if ($product->stock < $quantity) {
            $this->emit('no-stock', 'Stock insuficiente');
            return;
        }

        $exist = $this->posCart->firstWhere('id', $product->id);

        if ($exist) {
            // Si ya existe, aumenta la cantidad
            $this->increaseQty($product->id, $quantity);
            return;
        }

        // Agrega un nuevo producto al carrito
        $this->posCart->push([
            'id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'quantity' => $quantity,
            'price' => $product->price,
            'total' => $product->price * $quantity,
        ]);

        $this->updateCart();
        $this->emit('scan-ok', 'Producto agregado');
    }

    public function addWeightProduct($barcode)
    {
        $barcode = (string) $barcode;
        $productCode = ltrim(substr($barcode, 1, 6), '0');
        $weight = (float) substr($barcode, 7, 6) / 1000;

        $product = Product::where('barcode', $productCode)->first();

        if (!$product || !$product->is_weighted) {
            $this->emit('scan-notfound', 'El producto no es válido para ventas por peso.');
            return;
        }

        if ($product->stock < $weight) {
            $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '".');
            return;
        }

        $exist = $this->posCart->firstWhere('id', $product->id);

        if ($exist) {
            $this->posCart = $this->posCart->map(function ($item) use ($product, $weight) {
                if ($item['id'] === $product->id) {
                    $currentQuantity = $item['quantity'] ?? 0;  // Valor predeterminado de 0 si no existe la clave
                    $item['quantity'] = $currentQuantity + $weight;
                    $item['total'] = $item['quantity'] * $product->price_per_kg;
                }
                return $item;
            });
        } else {
            $this->posCart->push([
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'quantity' => $weight,
                'price' => $product->price_per_kg,
                'total' => $weight * $product->price_per_kg,
            ]);
        }

        $this->updateCart();
        $this->emit('scan-ok', $exist ? 'Cantidad actualizada' : 'Producto agregado al carrito por peso.');
    }

    private function isWeightedProductBarcode($barcode)
    {
        return substr($barcode, 0, 1) == '2'; // Condición que indica que es un producto por peso
    }

    private function updateCart()
    {
        // Actualiza el estado del carrito
        session()->put('posCart', $this->posCart);
    }

    public function updateQuantity($productId, $quantity)
    {
        $this->posCart = $this->posCart->map(function ($item) use ($productId, $quantity) {
            if ($item['id'] == $productId) {
                $item['quantity'] = $quantity;
                $item['total'] = $item['quantity'] * $item['price'];
            }
            return $item;
        });

        $this->updateCart();
    }

    public function removeItem($productId)
    {
        $this->posCart = $this->posCart->reject(function ($item) use ($productId) {
            return $item['id'] == $productId;
        });

        $this->updateCart();
    }

    public function decreaseQty($productId)
    {
        $this->posCart = $this->posCart->map(function ($item) use ($productId) {
            if ($item['id'] == $productId && $item['quantity'] > 1) {
                $item['quantity']--;
                $item['total'] = $item['quantity'] * $item['price'];
            }
            return $item;
        });

        $this->updateCart();
    }

    public function increaseQty($productId)
    {
        $this->posCart = $this->posCart->map(function ($item) use ($productId) {
            if ($item['id'] == $productId) {
                $item['quantity']++;
                $item['total'] = $item['quantity'] * $item['price'];
            }
            return $item;
        });

        $this->updateCart();
    }

    public function totalCart()
    {
        return $this->posCart->sum(function ($item) {
            // Verifica si la clave 'price' existe en el array $item
            if (isset($item['price']) && isset($item['quantity'])) {
                return $item['price'] * $item['quantity'];
            }
            return 0; // Devuelve 0 si la clave 'price' o 'quantity' no existe
        });
    }

    public function saveSale()
    {
        if (!$this->validateSale()) {
            return;
        }

        DB::beginTransaction();

        try {
            $sale = Sale::create($this->getSaleData());

            foreach ($this->posCart as $item) {
                $this->createSaleDetail($sale->id, $item);
                $this->updateProductStock($item);
            }

            DB::commit();
            $this->finalizeSale($sale);
        } catch (Exception $e) {
            DB::rollBack();
            $this->emit('sale-error', $e->getMessage());
        }
    }

    private function validateSale()
    {
        if ($this->total <= 0) {
            $this->emit('sale-error', 'AGREGA PRODUCTOS A LA VENTA');
            return false;
        }
        if ($this->cash <= 0 || $this->total > $this->cash) {
            $this->emit('sale-error', 'INGRESA EFECTIVO SUFICIENTE');
            return false;
        }
        if ($this->customerId === null) {
            $this->emit('sale-error', 'DEBES SELECCIONAR UN CLIENTE');
            return false;
        }
        return true;
    }

    private function getSaleData()
    {
        return [
            'total' => $this->total,
            'items' => $this->itemsQuantity,
            'cash' => $this->cash,
            'change' => $this->cash - $this->total,
            'user_id' => Auth::id(),
            'customer_id' => $this->customerId,
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'discount' => $this->discount,
            'discount_total' => $this->discount_total
        ];
    }

    public function createSaleDetail($saleId, $item)
    {
        SaleDetail::create([
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'sub_total' => $item['price'] * $item['quantity'],
            'product_id' => $item['id'],
            'sale_id' => $saleId
        ]);
    }

    public function updateProductStock($item)
    {
        $product = Product::find($item['id']);
        $product->stock -= $item['quantity'];
        $product->save();
    }

    public function finalizeSale($sale)
    {
        $this->clearCart();
        $this->emit('sale-ok', 'Venta realizada');
        $this->dispatchBrowserEvent('print-json', ['data' => $this->jsonData2($sale->id)]);
        $this->assignOccasionalCustomer();
    }

    public function clearCart()
    {
        $this->posCart = collect([]);
        $this->save();
    }

    public function setCustomer($customerId, $customerName)
    {
        $this->customerId = $customerId;
        $this->customerName = $customerName;
    }
    
}