<?php

namespace App\Exceptions;

/**
 * Lempar saat jam melewati batas sebelum commit transaksi webhook.
 * Controller menangkap, rollback (termasuk claim outbox), tulis
 * provider_timeout claim dalam transaksi BARU, lalu return 503.
 */
final class PreCommitTimeoutException extends \RuntimeException {}
