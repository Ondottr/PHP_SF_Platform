<?php declare(strict_types=1);

namespace PHP_SF\System\Core\Cache;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use PHP_SF\Cache\Exception\InvalidCacheArgumentException;

final class DoctrineResultCache implements DriverResult
{
    /**
     * @var Collection<int, list<mixed>>|null
     */
    private ?Collection $numericResult;
    /**
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $associativeResult;
    /**
     * @var Collection<int, mixed>|null
     */
    private ?Collection $fetchOneResult;


    public function __construct(private readonly string $cacheKey)
    {
        $this->numericResult = new ArrayCollection();
        $this->associativeResult = new ArrayCollection();
        $this->fetchOneResult = new ArrayCollection();

        if (false === ca()->has($this->cacheKey)) {
            throw new InvalidCacheArgumentException('Cache key does not exist!');
        }

        $this->setAll();
    }


    /**
     * Returns the next row of the result as a numeric array or FALSE if there are no more rows.
     *
     * @return list<mixed>|false
     */
    public function fetchNumeric(): array|false
    {
        $result = $this->numericResult?->current() ?? false;

        $this->next();

        return $result;
    }

    /**
     * Returns the next row of the result as an associative array or FALSE if there are no more rows.
     *
     * @return array<string,mixed>|false
     */
    public function fetchAssociative(): array|false
    {
        $result = $this->associativeResult?->current() ?? false;

        $this->next();

        return $result;
    }

    /**
     * Returns the first value of the next row of the result or FALSE if there are no more rows.
     *
     * @return mixed|false
     */
    public function fetchOne(): mixed
    {
        $result = $this->fetchOneResult?->current();

        $this->next();

        return $result;
    }

    /**
     * Returns an array containing all of the result rows represented as numeric arrays.
     *
     * @return list<list<mixed>>
     */
    public function fetchAllNumeric(): array
    {
        if (null === $this->numericResult) {
            return [];
        }

        $result = $this->numericResult->getValues();

        $this->free();

        return $result;
    }

    /**
     * Returns an array containing all of the result rows represented as associative arrays.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchAllAssociative(): array
    {
        if (null === $this->associativeResult) {
            return [];
        }

        $result = $this->associativeResult->getValues();

        $this->free();

        return $result;
    }

    /**
     * Returns an array containing the values of the first column of the result.
     *
     * @return list<mixed>
     */
    public function fetchFirstColumn(): array
    {
        if (null === $this->fetchOneResult) {
            return [];
        }

        $result = $this->fetchOneResult->getValues();

        $this->free();

        return $result;
    }

    /**
     * Returns the number of rows affected by the DELETE, INSERT, or UPDATE statement that produced the result.
     *
     * If the statement executed a SELECT query or a similar platform-specific SQL (e.g. DESCRIBE, SHOW, etc.),
     * some database drivers may return the number of rows returned by that query. However, this behaviour
     * is not guaranteed for all drivers and should not be relied on in portable applications.
     *
     * @return int the number of rows
     */
    public function rowCount(): int
    {
        return count($this->fetchAllNumeric());
    }

    /**
     * Returns the number of columns in the result.
     *
     * @return int The number of columns in the result. If the columns cannot be counted,
     *             this method must return 0.
     */
    public function columnCount(): int
    {
        return count($this->fetchOne());
    }

    /**
     * Discards the non-fetched portion of the result, enabling the originating statement to be executed again.
     */
    public function free(): void
    {
        $this->numericResult = null;
        $this->associativeResult = null;
        $this->fetchOneResult = null;
    }

    private function setAll(): void
    {
        if (($result = ca()->get($this->cacheKey)) === null) {
            $result = '[]';
        }

        /** @var list<array<string, mixed>> $cachedValue */
        $cachedValue = j_decode($result, true);

        $this->associativeResult = new ArrayCollection($cachedValue);

        $res = [];
        foreach ($cachedValue as $values) {
            $res[] = array_values($values);
        }

        $this->numericResult = new ArrayCollection($res);

        $res = [];
        foreach ($cachedValue as $values) {
            if (false === empty($values)) {
                $res[] = $values[array_key_first($values)];
            }
        }

        $this->fetchOneResult = new ArrayCollection($res);
    }

    private function next(): void
    {
        if (null === $this->numericResult || null === $this->associativeResult || null === $this->fetchOneResult) {
            return;
        }

        $this->numericResult->remove($this->numericResult->key());
        $this->associativeResult->remove($this->associativeResult->key());
        $this->fetchOneResult->remove($this->fetchOneResult->key());

        $this->numericResult->next();
        $this->associativeResult->next();
        $this->fetchOneResult->next();
    }
}
