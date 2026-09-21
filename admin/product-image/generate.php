<?php

defined('ABSPATH') || exit;

/**
 * Compose the main images of the given products into one image and set it as the bundle's product image.
 *
 * @param list<int> $item_ids Bundle product IDs in display order, paid before free.
 *
 * @return int|WP_Error Attachment ID of the generated image.
 */
function wc_bundles_generate_image(int $bundle_id, array $item_ids): int|WP_Error {
    // Products per row for each image count, so rows always fill the width
    $layouts = [1 => [1], 2 => [2], 3 => [1, 2], 4 => [2, 2], 5 => [2, 3], 6 => [3, 3]];
    $size = 2000;
    $files = [];
    $image_ids = [];

    foreach ($item_ids as $item_id) {
        $item = wc_get_product($item_id);
        $image_id = $item && wc_products_array_filter_readable($item) ? (int)$item->get_image_id() : 0;
        $file = $image_id ? get_attached_file($image_id) : false;

        // Reading the header is enough to settle the layout before decoding anything
        if ($file && @getimagesize($file)) {
            $files[] = $file;
            $image_ids[] = $image_id;
        }

        if (count($files) === count($layouts)) {
            break;
        }
    }

    if (!$files) {
        return new WP_Error('wc_bundles_no_images', __('None of the bundle products has a product image.', 'wc-bundles'));
    }

    // An image file replaced under the same attachment ID keeps the signature, delete the generated image to refresh
    $signature = implode(',', $image_ids);
    $previous_id = (int)get_post_meta($bundle_id, '_wc_bundles_generated_image_id', true);

    // The image generated last time was made from these same images, so attach it again instead of rebuilding it
    if ($previous_id && get_post_meta($previous_id, '_wc_bundles_generated', true) === $signature && is_file((string)get_attached_file($previous_id))) {
        set_post_thumbnail($bundle_id, $previous_id);

        return $previous_id;
    }

    wp_raise_memory_limit('image');

    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    $rows = $layouts[count($files)];
    $row_height = intdiv($size, count($rows));

    foreach ($rows as $row => $columns) {
        $tile_width = intdiv($size, $columns);

        for ($column = 0; $column < $columns; $column++) {
            // The last tile absorbs the pixels an uneven division leaves over
            $width = $column === $columns - 1 ? $size - $column * $tile_width : $tile_width;

            // Decode one image at a time, so memory peaks at a single source instead of all six
            $source = @imagecreatefromstring((string)file_get_contents(array_shift($files)));

            if ($source) {
                wc_bundles_draw_image_tile($canvas, $source, $column * $tile_width, $row * $row_height, $width, $row_height);
            }

            unset($source);
        }
    }

    $temporary = wp_tempnam('bundle-' . $bundle_id . '.jpg');

    if (!imagejpeg($canvas, $temporary, 90)) {
        wp_delete_file($temporary);

        return new WP_Error('wc_bundles_write_failed', __('The image could not be saved.', 'wc-bundles'));
    }

    unset($canvas);

    // Sideload like any upload, so plugins that filter or optimize uploads handle this image too
    $attachment_id = media_handle_sideload(
        ['name' => 'bundle-' . $bundle_id . '.jpg', 'tmp_name' => $temporary],
        $bundle_id,
        null,
        ['post_title' => get_the_title($bundle_id)]
    );

    if (is_wp_error($attachment_id)) {
        wp_delete_file($temporary);

        return $attachment_id;
    }

    // Replace the image generated last time instead of piling them up in the media library
    if ($previous_id && get_post_meta($previous_id, '_wc_bundles_generated', true)) {
        wp_delete_attachment($previous_id, true);
    }

    update_post_meta($attachment_id, '_wc_bundles_generated', $signature);
    update_post_meta($bundle_id, '_wc_bundles_generated_image_id', $attachment_id);
    set_post_thumbnail($bundle_id, $attachment_id);

    return $attachment_id;
}

/**
 * Fit the whole image inside the tile and extend its studio background over the rest, or crop busy photos to fill.
 */
function wc_bundles_draw_image_tile(GdImage $canvas, GdImage $source, int $x, int $y, int $width, int $height): void {
    $source_width = imagesx($source);
    $source_height = imagesy($source);
    $scale = min($width / $source_width, $height / $source_height);
    $fit_width = min($width, (int)round($source_width * $scale));
    $fit_height = min($height, (int)round($source_height * $scale));
    $left = intdiv($width - $fit_width, 2);
    $top = intdiv($height - $fit_height, 2);

    $plain = (!$left || (wc_bundles_is_plain_edge($source, 0, true) && wc_bundles_is_plain_edge($source, $source_width - 1, true)))
             && (!$top || (wc_bundles_is_plain_edge($source, 0, false) && wc_bundles_is_plain_edge($source, $source_height - 1, false)));

    if (!$plain) {
        $crop_width = min($source_width, (int)round($source_height * $width / $height));
        $crop_height = min($source_height, (int)round($source_width * $height / $width));

        imagecopyresampled($canvas, $source, $x, $y, intdiv($source_width - $crop_width, 2), intdiv($source_height - $crop_height, 4), $width, $height, $crop_width, $crop_height);

        return;
    }

    if ($left) {
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $left, $height, 1, $source_height);
        imagecopyresampled($canvas, $source, $x + $left + $fit_width, $y, $source_width - 1, 0, $width - $left - $fit_width, $height, 1, $source_height);
    }

    if ($top) {
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $top, $source_width, 1);
        imagecopyresampled($canvas, $source, $x, $y + $top + $fit_height, 0, $source_height - 1, $width, $height - $top - $fit_height, $source_width, 1);
    }

    imagecopyresampled($canvas, $source, $x + $left, $y + $top, 0, 0, $fit_width, $fit_height, $source_width, $source_height);
}

/**
 * Check that a column or row of pixels changes only gradually, as a studio background does.
 */
function wc_bundles_is_plain_edge(GdImage $source, int $position, bool $is_column): bool {
    $length = $is_column ? imagesy($source) : imagesx($source);
    $samples = 48;
    $previous = null;

    for ($i = 0; $i < $samples; $i++) {
        $offset = intdiv(($length - 1) * $i, $samples - 1);
        $color = imagecolorsforindex($source, imagecolorat($source, $is_column ? $position : $offset, $is_column ? $offset : $position));

        if ($previous && max(abs($color['red'] - $previous['red']), abs($color['green'] - $previous['green']), abs($color['blue'] - $previous['blue'])) > 16) {
            return false;
        }

        $previous = $color;
    }

    return true;
}
