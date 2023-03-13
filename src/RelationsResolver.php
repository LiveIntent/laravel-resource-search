<?php

namespace LiveIntent\LaravelResourceSearch;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationsResolver
{
    /**
     * @var array
     */
    private $includableRelations;

    /**
     * @var array
     */
    private $alwaysIncludedRelations;

    public function __construct(array $includableRelations = [], array $alwaysIncludedRelations = [])
    {
        $this->includableRelations = $includableRelations;
        $this->alwaysIncludedRelations = $alwaysIncludedRelations;
    }

    /**
     * Build the list of relations allowed to be included together with a resource based on the "include" query parameter.
     */
    public function requestedRelations(Request $request): array
    {
        $requestedIncludesStr = $request->get('include', '');
        $requestedIncludes = explode(',', $requestedIncludesStr);

        $allowedIncludes = array_unique(array_merge($this->includableRelations, $this->alwaysIncludedRelations));

        $validatedIncludes = [];

        foreach ($requestedIncludes as $requestedInclude) {
            if (in_array($requestedInclude, $allowedIncludes, true)) {
                $validatedIncludes[] = $requestedInclude;
            }

            if (strpos($requestedInclude, '.') !== false) {
                $relations = explode('.', $requestedInclude);
                $relationMatcher = '';

                foreach ($relations as $relation) {
                    $relationMatcher .= "{$relation}.";

                    if (in_array("{$relationMatcher}*", $allowedIncludes, true)) {
                        $validatedIncludes[] = $requestedInclude;
                    }
                }
            }
        }

        return array_unique(array_merge($validatedIncludes, $this->alwaysIncludedRelations));
    }

    /**
     * Resolves relation name from the given param constraint.
     */
    public function relationFromParamConstraint(string $paramConstraint): string
    {
        $paramConstraintParts = explode('.', $paramConstraint);

        return implode('.', array_slice($paramConstraintParts, 0, count($paramConstraintParts) - 1));
    }

    /**
     * Resolves relation field from the given param constraint.
     */
    public function relationFieldFromParamConstraint(string $paramConstraint): string
    {
        return Arr::last(explode('.', $paramConstraint));
    }

    /**
     * Resolved relation table name from the given relation instance.
     */
    public function relationTableFromRelationInstance(Relation $relationInstance): string
    {
        return $relationInstance->getModel()->getTable();
    }

    /**
     * Resolves relation foreign key from the given relation instance.
     */
    public function relationForeignKeyFromRelationInstance(Relation $relationInstance): string
    {
        $laravelVersion = (float) app()->version();

        return $laravelVersion > 5.7 || get_class($relationInstance) === HasOne::class ? $relationInstance->getQualifiedForeignKeyName() : $relationInstance->getQualifiedForeignKey();
    }

    /**
     * Resolves relation local key from the given relation instance.
     */
    public function relationLocalKeyFromRelationInstance(Relation $relationInstance): string
    {
        switch (get_class($relationInstance)) {
            case HasOne::class:
            case MorphOne::class:
                return $relationInstance->getParent()->getTable().'.'.$relationInstance->getLocalKeyName();

                break;
            case BelongsTo::class:
            case MorphTo::class:
                return $relationInstance->getQualifiedOwnerKeyName();

                break;
            default:
                return $relationInstance->getQualifiedLocalKeyName();

                break;
        }
    }

    /**
     * Removes loaded relations that were not requested and exposed on the given collection of entities.
     */
    public function guardRelationsForCollection(Collection $entities, array $requestedRelations, bool $normalized = false): Collection
    {
        return $entities->transform(
            function ($entity) use ($requestedRelations, $normalized) {
                return $this->guardRelations($entity, $requestedRelations, $normalized);
            }
        );
    }

    /**
     * Removes loaded relations that were not requested and exposed on the given entity.
     */
    public function guardRelations(Model $entity, array $requestedRelations, bool $normalized = false): Model
    {
        if (! $normalized) {
            $requestedRelations = $this->normalizeRequestedRelations($requestedRelations);
        }

        $relations = $entity->getRelations();
        ksort($relations);

        foreach ($relations as $relationName => $relation) {
            if ($relationName === 'pivot') {
                continue;
            }

            if (! array_key_exists($relationName, $requestedRelations)) {
                unset($relations[$relationName]);
            } elseif ($relation !== null) {
                if ($relation instanceof Model) {
                    $relation = $this->guardRelations($relation, $requestedRelations[$relationName], true);
                } else {
                    $relation = $this->guardRelationsForCollection($relation, $requestedRelations[$relationName], true);
                }
            }
        }

        $entity->setRelations($relations);

        return $entity;
    }

    protected function normalizeRequestedRelations(array $requestedRelations): array
    {
        $normalizedRelations = [];

        foreach ($requestedRelations as $requestedRelation) {
            if (($firstDotIndex = strpos($requestedRelation, '.')) !== false) {
                $parentOfNestedRelation = Arr::first(explode('.', $requestedRelation));
                $nestedRelation = substr($requestedRelation, $firstDotIndex + 1);

                $normalizedNestedRelations = $this->normalizeRequestedRelations([$nestedRelation]);

                $normalizedRelations[$parentOfNestedRelation] = array_merge_recursive(
                    Arr::get($normalizedRelations, $parentOfNestedRelation, []),
                    $normalizedNestedRelations
                );
            } elseif (! array_key_exists($requestedRelation, $normalizedRelations)) {
                $normalizedRelations[$requestedRelation] = [];
            }
        }

        return $normalizedRelations;
    }
}
