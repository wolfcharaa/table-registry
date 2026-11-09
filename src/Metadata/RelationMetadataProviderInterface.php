<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Metadata;

/**
 * @api
 */
interface RelationMetadataProviderInterface
{
    /**
     * @param class-string $model
     */
    public function metadata(string $model): ?RelationMetadata;
}
