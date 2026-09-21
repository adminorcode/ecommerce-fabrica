<?php

declare(strict_types=1);

namespace Petshop\Core\Admin;

use Petshop\Core\CategoryIcons;

defined('ABSPATH') || exit;

final class CategoryTermMeta
{
    public static function renderAddCategoryFields(): void
    {
        wp_nonce_field('petshop_category_fields', 'petshop_category_nonce');
        ?>
        <div class="form-field">
            <label for="petshop_menu_order"><?php esc_html_e('Ordem comercial', 'petshop-core'); ?></label>
            <input type="number" min="0" name="petshop_menu_order" id="petshop_menu_order" value="0">
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" name="petshop_seasonal" value="1">
                <?php esc_html_e('Categoria sazonal', 'petshop-core'); ?>
            </label>
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" name="petshop_visible_in_menu" value="1" checked>
                <?php esc_html_e('Exibir na navegação', 'petshop-core'); ?>
            </label>
        </div>
        <div class="form-field">
            <label>
                <?php esc_html_e('Ícone personalizado da vitrine', 'petshop-core'); ?>
            </label>
            <?php CategoryIcons::renderCustomAttachmentField(0); ?>
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Ícone da vitrine', 'petshop-core'); ?></label>
            <?php CategoryIcons::renderPicker(''); ?>
        </div>
        <?php
    }

    public static function renderEditCategoryFields(\WP_Term $term): void
    {
        wp_nonce_field('petshop_category_fields', 'petshop_category_nonce');
        $order = (int) get_term_meta($term->term_id, 'petshop_menu_order', true);
        $seasonal = (bool) get_term_meta($term->term_id, 'petshop_seasonal', true);
        $visible = (bool) get_term_meta($term->term_id, 'petshop_visible_in_menu', true);
        $icon = (string) get_term_meta($term->term_id, CategoryIcons::META_KEY, true);
        $attachmentId = absint(get_term_meta($term->term_id, CategoryIcons::ATTACHMENT_META_KEY, true));
        ?>
        <tr class="form-field">
            <th scope="row"><label for="petshop_menu_order"><?php esc_html_e('Ordem comercial', 'petshop-core'); ?></label></th>
            <td><input type="number" min="0" name="petshop_menu_order" id="petshop_menu_order" value="<?php echo esc_attr((string) $order); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Sazonalidade', 'petshop-core'); ?></th>
            <td>
                <label><input type="checkbox" name="petshop_seasonal" value="1" <?php checked($seasonal); ?>> <?php esc_html_e('Categoria sazonal', 'petshop-core'); ?></label><br>
                <label><input type="checkbox" name="petshop_visible_in_menu" value="1" <?php checked($visible); ?>> <?php esc_html_e('Exibir na navegação', 'petshop-core'); ?></label>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <?php esc_html_e('Ícone personalizado da vitrine', 'petshop-core'); ?>
            </th>
            <td><?php CategoryIcons::renderCustomAttachmentField($attachmentId); ?></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Ícone da vitrine', 'petshop-core'); ?></th>
            <td><?php CategoryIcons::renderPicker($icon); ?></td>
        </tr>
        <?php
    }

    public static function saveCategoryFields(int $termId): void
    {
        if (
            !isset($_POST['petshop_category_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['petshop_category_nonce'])),
                'petshop_category_fields'
            )
            || !current_user_can('manage_woocommerce')
        ) {
            return;
        }

        update_term_meta($termId, 'petshop_menu_order', isset($_POST['petshop_menu_order']) ? absint($_POST['petshop_menu_order']) : 0);
        update_term_meta($termId, 'petshop_seasonal', isset($_POST['petshop_seasonal']));
        update_term_meta($termId, 'petshop_visible_in_menu', isset($_POST['petshop_visible_in_menu']));

        if (array_key_exists(CategoryIcons::ATTACHMENT_META_KEY, $_POST)) {
            $attachmentId = absint(wp_unslash($_POST[CategoryIcons::ATTACHMENT_META_KEY]));
            if ($attachmentId <= 0 || !CategoryIcons::isUsableIconAttachment($attachmentId)) {
                delete_term_meta($termId, CategoryIcons::ATTACHMENT_META_KEY);
            } else {
                update_term_meta($termId, CategoryIcons::ATTACHMENT_META_KEY, $attachmentId);
            }
        }

        if (array_key_exists(CategoryIcons::META_KEY, $_POST)) {
            $icon = sanitize_key((string) wp_unslash($_POST[CategoryIcons::META_KEY]));
            if ($icon === '' || !CategoryIcons::isValid($icon)) {
                delete_term_meta($termId, CategoryIcons::META_KEY);
            } else {
                update_term_meta($termId, CategoryIcons::META_KEY, $icon);
            }
        }
    }
}
