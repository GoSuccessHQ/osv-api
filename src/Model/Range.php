<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Internal\Read;

/**
 * A range of affected versions as a list of events, e.g. "introduced 0, fixed
 * 2.4.58-1ubuntu8.19". A range without a `fixed` event has no fix yet.
 */
final readonly class Range
{
    /**
     * @param string|null $type   `ECOSYSTEM`, `SEMVER` or `GIT`
     * @param list<Event> $events
     */
    public function __construct(
        public ?string $type,
        public ?string $repo,
        public array $events,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(
            Read::string($data['type'] ?? null),
            Read::string($data['repo'] ?? null),
            array_values(array_map(Event::fromArray(...), Read::array($data['events'] ?? null))),
        );
    }

    /**
     * The versions that fix the range, in the order of the events.
     *
     * @return list<string>
     */
    public function fixedVersions(): array
    {
        $fixed = [];

        foreach ($this->events as $event) {
            if ($event->fixed !== null) {
                $fixed[] = $event->fixed;
            }
        }

        return $fixed;
    }
}
