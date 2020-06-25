<?php

/**
 * Plugin Name: Portfolios
 * Description: A WordPress CPT for portfolios.
 * Version: 1.0.0
 * Author: James Boynton
 */

namespace Xzito\Portfolios;

$autoload_path = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload_path)) {
  require_once($autoload_path);
}

new Portfolios();
