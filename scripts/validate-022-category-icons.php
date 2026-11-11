<?php

defined('ABSPATH') || exit(1);

if (!defined('WP_CLI') || !WP_CLI || !class_exists('WooCommerce')) {
    throw new RuntimeException('Execute esta validacao com WP-CLI e WooCommerce ativo.');
}

use Petshop\Core\CategoryIcons;
use Petshop\Core\Storefront\CategoryGrid;

$failures = [];

$term = get_term_by('slug', 'bandanas', 'product_cat');
if (!$term instanceof WP_Term) {
    WP_CLI::error('Categoria bandanas ausente para validacao do Plano 022.');
}

$previousAttachment = absint(get_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY, true));
$previousThumbnail = absint(get_term_meta($term->term_id, 'thumbnail_id', true));
$createdAttachmentIds = [];

$createPngAttachment = static function (string $basename, int $r, int $g, int $b) use (&$createdAttachmentIds): int {
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Extensao GD necessaria para gerar PNG de teste.');
    }

    $image = imagecreatetruecolor(256, 256);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    $fill = imagecolorallocatealpha($image, $r, $g, $b, 0);
    imagefilledellipse($image, 128, 128, 160, 160, $fill);

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Upload dir indisponivel: ' . $uploads['error']);
    }

    $filename = trailingslashit($uploads['path']) . $basename . '-' . wp_generate_password(6, false) . '.png';
    if (!imagepng($image, $filename)) {
        imagedestroy($image);
        throw new RuntimeException('Falha ao gravar PNG de teste.');
    }
    imagedestroy($image);

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => $basename,
        'post_status' => 'inherit',
        'post_content' => '',
    ], $filename);

    if (is_wp_error($attachmentId) || $attachmentId <= 0) {
        throw new RuntimeException('Falha ao criar attachment de teste.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachmentId, $filename);
    if (is_array($metadata) && $metadata !== []) {
        wp_update_attachment_metadata($attachmentId, $metadata);
    }

    $createdAttachmentIds[] = (int) $attachmentId;

    return (int) $attachmentId;
};

try {
    $galleryBefore = CategoryIcons::resolveDisplayForTerm($term);
    if ($galleryBefore['source'] !== 'gallery' || $galleryBefore['url'] === '') {
        $failures[] = 'Sem icone personalizado, bandanas deveria usar galeria/fallback.';
    }

    $customIconId = $createPngAttachment('petshop-022-custom-icon', 18, 103, 106);
    $thumbnailId = $createPngAttachment('petshop-022-thumbnail', 233, 83, 13);

    update_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY, $customIconId);
    update_term_meta($term->term_id, 'thumbnail_id', $thumbnailId);

    if (!CategoryIcons::isUsableIconAttachment($customIconId)) {
        $failures[] = 'PNG transparente valido deveria ser aceito como icone personalizado.';
    }

    $withCustom = CategoryIcons::resolveDisplayForTerm($term);
    if ($withCustom['source'] !== 'attachment' || $withCustom['attachment_id'] !== $customIconId) {
        $failures[] = 'Resolve deveria priorizar attachment personalizado.';
    }
    $attachedFile = get_attached_file($customIconId);
    if (
        $withCustom['url'] === ''
        || $attachedFile === false
        || !str_contains($withCustom['url'], wp_basename($attachedFile))
    ) {
        $failures[] = 'URL do icone personalizado ausente ou invalida.';
    }

    $markup = CategoryGrid::renderCategoryGrid(['limit' => 12]);
    if (!str_contains($markup, 'petshop-category-card__icon--media')) {
        $failures[] = 'Grade deveria renderizar icone personalizado como <img>.';
    }
    if (!str_contains($markup, esc_url($withCustom['url']))) {
        $failures[] = 'Markup da grade nao inclui a URL do attachment personalizado.';
    }

    $thumbnailUrl = (string) wp_get_attachment_url($thumbnailId);
    if ($thumbnailUrl !== '' && str_contains($markup, $thumbnailUrl)) {
        $failures[] = 'Miniatura WooCommerce nao deve aparecer como icone da Home.';
    }

    update_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY, 999999001);
    $invalid = CategoryIcons::resolveDisplayForTerm($term);
    if ($invalid['source'] !== 'gallery') {
        $failures[] = 'Attachment inexistente deveria cair no fallback da galeria.';
    }

    delete_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY);
    $restored = CategoryIcons::resolveDisplayForTerm($term);
    if ($restored['source'] !== 'gallery' || $restored['url'] === '') {
        $failures[] = 'Remover icone personalizado deveria restaurar galeria/fallback.';
    }

    $restoredMarkup = CategoryGrid::renderCategoryGrid(['limit' => 12]);
    if (str_contains($restoredMarkup, esc_url($withCustom['url']))) {
        $failures[] = 'Apos remocao, a grade nao deveria manter a URL do attachment personalizado.';
    }

    if (!metadata_exists('term', $term->term_id, CategoryIcons::META_KEY) && CategoryIcons::resolveForTerm($term) === '') {
        $failures[] = 'Fallback por slug deveria continuar disponivel sem migracao manual.';
    }
} finally {
    if ($previousAttachment > 0) {
        update_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY, $previousAttachment);
    } else {
        delete_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY);
    }

    if ($previousThumbnail > 0) {
        update_term_meta($term->term_id, 'thumbnail_id', $previousThumbnail);
    } else {
        delete_term_meta($term->term_id, 'thumbnail_id');
    }

    foreach ($createdAttachmentIds as $attachmentId) {
        wp_delete_attachment($attachmentId, true);
    }
}

if ($failures !== []) {
    WP_CLI::error("Plano 022 falhou:\n- " . implode("\n- ", $failures));
}

WP_CLI::success('Icones personalizados da vitrine, fallback e independencia da miniatura aprovados.');
