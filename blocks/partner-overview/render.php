<?php
/**
 * Server-Side Render für den Partnerübersicht-Block.
 *
 * @var array $attributes Block-Attribute
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$attributes['variant'] = 'grid';

include KUH_THEME_DIR . '/blocks/partner-carousel/render.php';