<?php

/**
 * Plugin Name: Portfolio
 * Description: A WordPress CPT for a portfolio.
 * Version: 1.0.1
 * Author: James Boynton
 */

namespace Xzito\Portfolio;

$autoload_path = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload_path)) {
  require_once($autoload_path);
}

new Portfolio();
