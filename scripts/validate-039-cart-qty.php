<?php

/**
 * Plan 039 used an application-owned quantity interceptor. Plan 041 removes
 * that mechanism and validates that the Cart Block is the only cart owner.
 */
require __DIR__ . '/validate-041-shipping-destination.php';
