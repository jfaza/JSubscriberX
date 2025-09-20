<?php namespace JavidFazaeli\JSubscriberX\Libraries;

interface ProviderInterface {
    public function name(): string;                          // e.g. "mailchimp"
    public function configure(array $config): void;
    public function subscribe(array $subscriber): array;     // return shape below
    public function unsubscribe(string $email): array;
    public function upsertTags(string $email, array $tags): array;

    // Optional but useful hints
    public function doubleOptIn(): bool;                     // from config
    public function defaultTags(): array;                    // from config
}
