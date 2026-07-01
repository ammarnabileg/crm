<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Contract;

use Nizam\Platform\Plugin\PluginInterface;

/**
 * The SDK contract for a knowledge-source plugin.
 *
 * A knowledge-source plugin exposes a body of knowledge an agent may consult — a document corpus, a
 * knowledge base, a catalogue. Beyond the base {@see PluginInterface}, it must identify the knowledge
 * domains it covers so the platform can route retrieval queries to the sources most likely to answer
 * them.
 */
interface KnowledgeSourcePlugin extends PluginInterface
{
    /**
     * The knowledge domains this source covers (e.g. `product-catalog`, `hr-policies`).
     *
     * @return list<string>
     */
    public function knowledgeDomains(): array;
}
