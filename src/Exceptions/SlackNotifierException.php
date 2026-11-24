<?php

namespace Stasadev\SlackNotifier\Exceptions;

use Throwable;

/**
 * Marks the exceptions of this package, which are reported and never sent to Slack.
 */
interface SlackNotifierException extends Throwable {}
