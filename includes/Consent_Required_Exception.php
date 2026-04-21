<?php

namespace Adbot;

/**
 * Thrown when code attempts to contact an external service before the
 * site administrator has granted consent.
 *
 * Controllers should catch this and return a 403 WP_Error response.
 */
class Consent_Required_Exception extends \RuntimeException {
}
