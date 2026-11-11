<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute com WP-CLI e WooCommerce.');
}

use Petshop\Core\Personalization\Application\CreateDraft;
use Petshop\Core\Personalization\Application\SnapshotOrderItem;
use Petshop\Core\Personalization\Application\TransitionStatus;
use Petshop\Core\Personalization\Domain\PersonalizationStatus;
use Petshop\Core\Personalization\Infrastructure\PersonalizationRepository;
use Petshop\Core\Personalization\Infrastructure\PrivateStorage;
use Petshop\Core\Personalization\Infrastructure\ProductSettings;

$failures = [];
PrivateStorage::ensureReady();

$sku = 'PLAN012-BANDANA';
$productId = wc_get_product_id_by_sku($sku);
if ($productId <= 0 || !ProductSettings::isEnabledFor($productId)) {
    WP_CLI::error('Fixture PLAN012-BANDANA ausente ou não habilitada. Rode seed-012-personalizable-products.php.');
}

$settings = ProductSettings::forProduct($productId);
$spec = $settings->specification();
if (!$spec) {
    WP_CLI::error('Spec inválida para PLAN012-BANDANA.');
}

$previewWidth = 64;
$previewHeight = 64;
$previewImage = imagecreatetruecolor($previewWidth, $previewHeight);
$bg = imagecolorallocate($previewImage, 20, 100, 100);
imagefill($previewImage, 0, 0, $bg);
ob_start();
imagepng($previewImage);
$previewPng = ob_get_clean();
imagedestroy($previewImage);
$previewDataUrl = 'data:image/png;base64,' . base64_encode($previewPng);

$prodImage = imagecreatetruecolor($spec->widthPx(), $spec->heightPx());
$prodBg = imagecolorallocate($prodImage, 20, 100, 100);
imagefill($prodImage, 0, 0, $prodBg);
ob_start();
imagepng($prodImage);
$productionPng = ob_get_clean();
imagedestroy($prodImage);
$productionDataUrl = 'data:image/png;base64,' . base64_encode($productionPng);

try {
    $draft = CreateDraft::handle([
        'product_id' => $productId,
        'variation_id' => 0,
        'design' => [
            'schema' => 1,
            'objects' => [
                ['type' => 'text', 'text' => 'Luna', 'left' => 10, 'top' => 10],
            ],
        ],
        'preview_png' => $previewDataUrl,
        'production_png' => $productionDataUrl,
        'upload_token' => '',
    ]);
} catch (Throwable $error) {
    WP_CLI::error('CreateDraft falhou: ' . $error->getMessage());
}

if ($draft->status !== PersonalizationStatus::Draft) {
    $failures[] = 'Rascunho não ficou em draft.';
}

$order = wc_create_order(['customer_id' => 0]);
if (!$order instanceof WC_Order) {
    WP_CLI::error('Falha ao criar pedido HPOS de smoke.');
}

$itemId = $order->add_product(wc_get_product($productId), 1);
$item = $order->get_item($itemId);
if (!$item instanceof WC_Order_Item_Product) {
    WP_CLI::error('Item de pedido inválido.');
}

$item->update_meta_data(SnapshotOrderItem::META_PUBLIC_ID, $draft->publicId);
$item->update_meta_data(SnapshotOrderItem::META_SUMMARY, $draft->textSummary);
$item->update_meta_data(SnapshotOrderItem::META_HASH, $draft->snapshotHash);
$item->update_meta_data(SnapshotOrderItem::META_SCHEMA, (string) $draft->designSchemaVersion);
$item->save();
$order->set_status('processing');
$order->save();

SnapshotOrderItem::forOrder($order);
$linked = PersonalizationRepository::findByPublicId($draft->publicId);
if (!$linked instanceof \Petshop\Core\Personalization\Domain\Personalization) {
    $failures[] = 'Personalização sumiu após snapshot.';
} else {
    if ((int) $linked->orderId !== (int) $order->get_id()) {
        $failures[] = 'order_id não vinculado.';
    }
    if ($linked->status !== PersonalizationStatus::Review && $linked->status !== PersonalizationStatus::AwaitingPayment) {
        // Force payment path: awaiting_payment → review.
        if ($linked->status === PersonalizationStatus::Draft || $linked->status === PersonalizationStatus::Cart) {
            TransitionStatus::applyIfPossible($linked, PersonalizationStatus::AwaitingPayment, null, 'smoke');
            $linked = PersonalizationRepository::findByPublicId($draft->publicId) ?? $linked;
        }
        TransitionStatus::applyIfPossible($linked, PersonalizationStatus::Review, null, 'smoke paid');
        $linked = PersonalizationRepository::findByPublicId($draft->publicId);
    }
    if (!$linked || $linked->status !== PersonalizationStatus::Review) {
        $failures[] = 'Estado pós-pagamento não chegou em review (atual: ' . ($linked?->status->value ?? 'null') . ').';
    }
    $preview = PersonalizationRepository::file((int) $linked->id, 'preview');
    $production = PersonalizationRepository::file((int) $linked->id, 'production');
    if ($preview === null || $production === null) {
        $failures[] = 'Arquivos preview/production ausentes.';
    }
}

$order->update_status('cancelled', 'Smoke 012 cleanup');
$order->save();
$afterCancel = PersonalizationRepository::findByPublicId($draft->publicId);
if ($afterCancel && $afterCancel->status->isActiveQueue()) {
    $failures[] = 'Cancelamento deixou item ativo na fila.';
}

WP_CLI::log(sprintf(
    'smoke-012: product=%d order=%d public_id=%s status=%s',
    $productId,
    (int) $order->get_id(),
    $draft->publicId,
    $afterCancel?->status->value ?? 'missing'
));

if ($failures !== []) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Smoke 012 falhou.');
}

WP_CLI::success('Smoke HPOS/pedido/fila do Plano 012 ok.');
