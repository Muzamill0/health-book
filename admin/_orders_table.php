<?php /** @var array $recent */ ?>
<?php if (!$recent): ?>
    <p class="muted">No orders yet.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead><tr><th>Order</th><th>Customer</th><th>Referred by</th><th>Purchased</th><th>Amount</th><th>Status</th><th>Invoice</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $o): ?>
        <tr>
            <td><strong><?= e($o['order_no']) ?></strong></td>
            <td>
                <?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['customer_email']) ?></small>
                <?php if ($o['paddle_transaction_id']): ?><br><a href="<?= e(paddle_dashboard_url('transaction', $o['paddle_transaction_id'])) ?>" target="_blank" rel="noopener"><small>Paddle ↗</small></a>
                <?php elseif ($o['stripe_customer_id']): ?><br><a href="https://dashboard.stripe.com/customers/<?= e($o['stripe_customer_id']) ?>" target="_blank" rel="noopener"><small>Stripe customer ↗</small></a><?php endif; ?>
            </td>
            <td><?php if (!empty($o['referrer_name'])): ?><span class="badge badge-ref">🔗 <?= e($o['referrer_name']) ?></span><?php else: ?><small class="muted">Direct</small><?php endif; ?></td>
            <td>
                <?php if (($o['order_type'] ?? 'bundle') === 'bundle'): ?><span class="badge badge-paid">Complete bundle</span>
                <?php else: ?><small title="<?= e($o['product_name']) ?>"><?= e(mb_strimwidth($o['product_name'], 0, 60, '…')) ?></small><?php endif; ?>
                <?php if ((float) ($o['discount_amount'] ?? 0) > 0 && ($o['order_type'] ?? '') === 'books'): ?><br><small class="muted">−<?= number_format((float) $o['discount_amount'], 2) ?> discount</small><?php endif; ?>
            </td>
            <td><?= number_format((float) $o['amount'], 2) ?> <?= e(strtoupper($o['currency'])) ?></td>
            <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span><?php if ($o['status'] === 'paid'): ?><br><small class="muted">⬇ <?= (int) $o['download_count'] ?> download<?= (int) $o['download_count'] === 1 ? '' : 's' ?></small><?php endif; ?></td>
            <td>
                <?php if ($o['invoice_number']): ?><strong><?= e($o['invoice_number']) ?></strong><br><?php endif; ?>
                <?php if ($o['invoice_pdf']): ?><a href="<?= e($o['invoice_pdf']) ?>" target="_blank" rel="noopener"><small>PDF</small></a><?php endif; ?>
                <?php if ($o['invoice_url']): ?> · <a href="<?= e($o['invoice_url']) ?>" target="_blank" rel="noopener"><small>View</small></a><?php endif; ?>
                <?php if (!$o['invoice_number']): ?><small class="muted">—</small><?php endif; ?>
            </td>
            <td><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
