<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use InvalidArgumentException;

/**
 * One installed package version to look up: ecosystem, package name and
 * version, exactly as OSV.dev expects them.
 */
final readonly class PackageQuery
{
    /**
     * @param non-empty-string $ecosystem e.g. {@see Ecosystem::GO} or `Ubuntu:24.04:LTS`
     * @param non-empty-string $name      package name in that ecosystem
     * @param non-empty-string $version   installed version, e.g. `1:9.6p1-3ubuntu13.20`
     */
    public function __construct(
        public string $ecosystem,
        public string $name,
        public string $version,
    ) {
        foreach (['ecosystem' => $ecosystem, 'name' => $name, 'version' => $version] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("{$field} must not be empty.");
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new InvalidArgumentException("{$field} must not contain control characters.");
            }
        }
    }

    /**
     * The query object of the API, with the page token of a follow-up request.
     *
     * @return array{package: array{name: string, ecosystem: string}, version: string, page_token?: string}
     *
     * @internal
     */
    public function toArray(?string $pageToken = null): array
    {
        $query = [
            'package' => ['name' => $this->name, 'ecosystem' => $this->ecosystem],
            'version' => $this->version,
        ];

        if ($pageToken !== null) {
            $query['page_token'] = $pageToken;
        }

        return $query;
    }
}
