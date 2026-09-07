<?php

namespace SalvatoreCervone\LogOperations\Attributes;

use Attribute;

/**
 * Attributo PHP 8 per il tracciamento dichiarativo delle classi o dei singoli metodi.
 *
 * Esempi di utilizzo:
 *
 * #[Traceable]
 * class PaymentGateway { ... }
 *
 * oppure su un singolo metodo:
 *
 * #[Traceable(label: 'Elaborazione Pagamento Stripe', level: 'core')]
 * public function charge(Order $order): PaymentResult { ... }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Traceable
{
    public function __construct(
        public ?string $label = null,
        public string $level = 'core',
        public array $tags = [],
        public bool $logArguments = true,
        public bool $logResult = true,
    ) {
    }
}
