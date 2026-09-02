<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\CancelOrderRequest;
use App\Http\Requests\Api\Admin\ListOrdersRequest;
use App\Http\Requests\Api\Admin\RejectPaymentRequest;
use App\Http\Requests\Api\Admin\TransitionOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The operational half of the panel: reading orders and moving them along.
 *
 * Owner and staff both get here — `staff` is an order-operations role, see
 * docs/decisions.md — which is why this controller sits outside the
 * `role:owner` group in routes/admin.php.
 *
 * Every action delegates to a lifecycle method on Order: the model is what
 * knows how to touch inventory and write the audit trail, and it takes the
 * order's row lock while it does. Nothing here decides whether a move is
 * legal; an illegal one surfaces as a 422 `invalid_order_transition`.
 */
class OrderController extends Controller
{
    public function __construct(private readonly CustomerNotificationService $notifications) {}

    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['baseCurrency', 'paymentCurrency', 'paymentMethod', 'fulfillmentMethod'])
            ->withCount('items')
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', (string) $request->string('status')),
            )
            ->when(
                $request->filled('search'),
                fn (Builder $query) => $this->applySearch($query, (string) $request->string('search')),
            )
            // Newest first: the panel is a work queue, not an archive.
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return $this->detail($order);
    }

    public function confirmPayment(Request $request, Order $order): OrderResource
    {
        $order->confirmPayment($this->admin($request));

        $this->notifications->notifyStatusChange($order->fresh());

        return $this->detail($order->fresh());
    }

    public function rejectPayment(RejectPaymentRequest $request, Order $order): OrderResource
    {
        $order->rejectPayment($this->admin($request), (string) $request->string('reason'));

        return $this->detail($order->fresh());
    }

    public function transition(TransitionOrderRequest $request, Order $order): OrderResource
    {
        $target = OrderStatus::from((string) $request->string('status'));

        $order->advanceTo(
            $target,
            $this->admin($request),
            $request->input('reason'),
            $request->shippingDetails(),
        );

        if (in_array($target, [OrderStatus::Shipped, OrderStatus::Delivered], true)) {
            $this->notifications->notifyStatusChange($order->fresh());
        }

        return $this->detail($order->fresh());
    }

    public function cancel(CancelOrderRequest $request, Order $order): OrderResource
    {
        $order->cancel($this->admin($request), (string) $request->string('reason'));

        return $this->detail($order->fresh());
    }

    /**
     * Every action answers with the same payload as the detail endpoint, so the
     * panel can replace what it is showing instead of fetching it again.
     */
    private function detail(Order $order): OrderResource
    {
        $order->load([
            'items',
            'baseCurrency',
            'paymentCurrency',
            'state',
            'municipality',
            'parish',
            'paymentMethod',
            'fulfillmentMethod',
            // Newest proof first: after a rejection there can be several, and
            // the last one is the one under review.
            'paymentProofs' => fn ($query) => $query->orderByDesc('submitted_at')->orderByDesc('id'),
            'statusHistory' => fn ($query) => $query->orderBy('id'),
            'statusHistory.changedBy',
        ]);

        return OrderResource::make($order);
    }

    /**
     * The identifiers an admin actually has at hand when a customer calls: the
     * order number, the name on the order, the document, or the phone.
     *
     * The term is escaped because `%` and `_` are wildcards to ILIKE — without
     * it, a search for `_` would match every order in the store.
     */
    private function applySearch(Builder $query, string $term): Builder
    {
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($pattern) {
            $query->where('order_number', 'ilike', $pattern)
                ->orWhere('customer_name', 'ilike', $pattern)
                ->orWhere('document_number', 'ilike', $pattern)
                ->orWhere('customer_phone', 'ilike', $pattern);
        });
    }

    /**
     * The route group guarantees an authenticated, active admin; this only
     * narrows the type for the lifecycle methods, which all record who acted.
     */
    private function admin(Request $request): User
    {
        return $request->user();
    }
}
