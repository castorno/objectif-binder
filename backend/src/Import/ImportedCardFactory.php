<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\CardFinish;
use App\Import\Exception\InvalidRecordException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Turns a raw record of an import source into an ImportedCard, or says what
 * is wrong with it. The expected shape of a record is described here and
 * nowhere else; docs/import.md documents it for people.
 */
final class ImportedCardFactory
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidRecordException
     */
    public function fromArray(array $data): ImportedCard
    {
        $violations = $this->validator->validate($data, $this->recordConstraint());

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $this->fieldName($violation->getPropertyPath()), $violation->getMessage());
        }

        if ([] !== $messages) {
            throw new InvalidRecordException($messages);
        }

        /**
         * @var array{
         *     game: array{slug: string, name: string, identityLabel?: ?string, identityGroupLabel?: ?string},
         *     set: array{code: string, name: string, releaseDate?: ?string},
         *     number: string,
         *     name: string,
         *     rarity?: ?string,
         *     externalId?: ?string,
         *     imageUrl?: ?string,
         *     largeImageUrl?: ?string,
         *     attributes?: ?array<string, mixed>,
         *     finishes?: ?list<string>,
         *     identities?: ?list<array{externalId: string, name: string, sortOrder?: ?int, group?: ?array{name: string, order?: ?int}}>,
         * } $data
         */
        $identities = [];
        foreach ($data['identities'] ?? [] as $identity) {
            // Listed twice, an identity still groups the card once.
            $identities[trim($identity['externalId'])] = new ImportedIdentity(
                trim($identity['externalId']),
                trim($identity['name']),
                $identity['sortOrder'] ?? null,
                isset($identity['group']) ? trim($identity['group']['name']) : null,
                $identity['group']['order'] ?? null,
            );
        }

        $releaseDate = $data['set']['releaseDate'] ?? null;

        return new ImportedCard(
            gameSlug: $data['game']['slug'],
            gameName: trim($data['game']['name']),
            gameIdentityLabel: $this->trimmed($data['game']['identityLabel'] ?? null),
            gameIdentityGroupLabel: $this->trimmed($data['game']['identityGroupLabel'] ?? null),
            setCode: trim($data['set']['code']),
            setName: trim($data['set']['name']),
            setReleaseDate: null === $releaseDate ? null : new \DateTimeImmutable($releaseDate.' 00:00:00'),
            number: trim($data['number']),
            name: trim($data['name']),
            rarity: $this->trimmed($data['rarity'] ?? null),
            externalId: $this->trimmed($data['externalId'] ?? null),
            imageUrl: $this->trimmed($data['imageUrl'] ?? null),
            largeImageUrl: $this->trimmed($data['largeImageUrl'] ?? null),
            attributes: $data['attributes'] ?? [],
            identities: array_values($identities),
            finishes: $this->finishes($data['finishes'] ?? null),
        );
    }

    private function recordConstraint(): Constraint
    {
        // The lengths are those of the columns the values end up in.
        return new Assert\Collection(fields: [
            'game' => new Assert\Required([
                new Assert\NotNull(),
                new Assert\Collection(fields: [
                    'slug' => new Assert\Required([
                        ...$this->text(100),
                        new Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'This value should be made of lower-case letters, digits and single hyphens.'),
                    ]),
                    'name' => new Assert\Required($this->text(100)),
                    'identityLabel' => new Assert\Optional($this->optionalText(50)),
                    'identityGroupLabel' => new Assert\Optional($this->optionalText(50)),
                ]),
            ]),
            'set' => new Assert\Required([
                new Assert\NotNull(),
                new Assert\Collection(fields: [
                    'code' => new Assert\Required($this->text(50)),
                    'name' => new Assert\Required($this->text(150)),
                    'releaseDate' => new Assert\Optional([
                        new Assert\Sequentially([new Assert\Type('string'), new Assert\Date(message: 'This value should be a date written as YYYY-MM-DD.')]),
                    ]),
                ]),
            ]),
            'number' => new Assert\Required($this->text(20)),
            'name' => new Assert\Required($this->text(200)),
            'rarity' => new Assert\Optional($this->optionalText(100)),
            'externalId' => new Assert\Optional($this->optionalText(100)),
            'imageUrl' => new Assert\Optional($this->imageAddress()),
            'largeImageUrl' => new Assert\Optional($this->imageAddress()),
            'attributes' => new Assert\Optional([new Assert\Type('array')]),
            'finishes' => new Assert\Optional([
                new Assert\Sequentially([
                    new Assert\Type('list'),
                    new Assert\All([new Assert\Choice(callback: [self::class, 'finishNames'], message: 'This value should be one of: {{ choices }}.')]),
                ]),
            ]),
            'identities' => new Assert\Optional([
                new Assert\Sequentially([
                    new Assert\Type('list'),
                    new Assert\All([
                        new Assert\Collection(fields: [
                            'externalId' => new Assert\Required($this->text(100)),
                            'name' => new Assert\Required($this->text(200)),
                            'sortOrder' => new Assert\Optional([new Assert\Type('int')]),
                            'group' => new Assert\Optional([
                                new Assert\Collection(fields: [
                                    'name' => new Assert\Required($this->text(100)),
                                    'order' => new Assert\Optional([new Assert\Type('int')]),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    /**
     * A required piece of text. Checked one rule at a time: the length of a
     * value that is not text cannot be measured.
     *
     * @param int<1, max> $maxLength
     *
     * @return list<Constraint>
     */
    private function text(int $maxLength): array
    {
        return [new Assert\Sequentially([
            new Assert\NotNull(),
            new Assert\Type('string'),
            new Assert\NotBlank(normalizer: trim(...)),
            new Assert\Length(max: $maxLength, normalizer: trim(...)),
        ])];
    }

    /**
     * A piece of text that may be left out or null, but not empty.
     *
     * @param int<1, max> $maxLength
     *
     * @return list<Constraint>
     */
    private function optionalText(int $maxLength): array
    {
        return [new Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Length(min: 1, max: $maxLength, normalizer: trim(...)),
        ])];
    }

    /**
     * The address of a picture, which a browser will be told to load. Only
     * plain https addresses: nothing else has a reason to be in an image
     * tag, and "javascript:" or "data:" addresses have none at all.
     *
     * @return list<Constraint>
     */
    private function imageAddress(): array
    {
        return [new Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Length(min: 1, max: 255),
            new Assert\Url(protocols: ['https'], requireTld: true, message: 'This value should be an https address.'),
        ])];
    }

    /**
     * @return list<string>
     */
    public static function finishNames(): array
    {
        return array_column(CardFinish::cases(), 'value');
    }

    /**
     * @param list<string>|null $names
     *
     * @return list<CardFinish>|null in the order of the enum, each one once
     */
    private function finishes(?array $names): ?array
    {
        if (null === $names) {
            return null;
        }

        return array_values(array_filter(CardFinish::cases(), static fn (CardFinish $finish): bool => \in_array($finish->value, $names, true)));
    }

    private function trimmed(?string $value): ?string
    {
        return null === $value ? null : trim($value);
    }

    /**
     * "[set][code]" as the validator writes it, to "set.code".
     */
    private function fieldName(string $propertyPath): string
    {
        return str_replace(['][', '[', ']'], ['.', '', ''], $propertyPath);
    }
}
