<?php /** @noinspection PhpClassHasTooManyDeclaredMembersInspection @noinspection PhpLackOfCohesionInspection */
declare(strict_types=1);

namespace PHP_SF\System\Traits;

use App\Kernel;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

trait EntityRepositoriesTrait
{
    /** @var array<class-string, AbstractEntityRepository<object>> */
    private static array $repositories = [];

    public static function find(int $id): ?static
    {
        /** @var static|null $entity */
        $entity = self::rep()->find($id);

        return $entity;
    }

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     */
    public static function findOneBy(array $criteria, ?array $orderBy = null): ?static
    {
        /** @var static|null $entity */
        $entity = self::rep()->findOneBy($criteria, $orderBy);

        return $entity;
    }

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return array<static>
     */
    public static function findBy(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        /** @var array<static> $arr */
        $arr = self::rep()->findBy($criteria, $orderBy, $limit, $offset);

        $res = [];
        foreach ($arr as $item) {
            $res[$item->getId()] = $item;
        }

        return $res;
    }

    /**
     * @return array<static>
     */
    public static function findAll(): array
    {
        /** @var array<static> $arr */
        $arr = self::rep()->findAll();

        $res = [];
        foreach ($arr as $item) {
            $res[$item->getId()] = $item;
        }

        return $res;
    }


    /**
     * @return AbstractEntityRepository<static>
     */
    public static function rep(): AbstractEntityRepository
    {
        if (false === array_key_exists(static::class, self::$repositories)) {
            self::setRepository();
        }

        /** @var AbstractEntityRepository<static> $repository */
        $repository = self::$repositories[static::class];

        return $repository;
    }

    private static function setRepository(): void
    {
        $projectDir = Kernel::getInstance()->getProjectDir();

        $yamlConfigCacheKey = '/config/packages/doctrine.yaml';

        // parse yaml doctrine config
        $config = ca()->get($yamlConfigCacheKey);
        if (null === $config) {
            $config = yaml_parse_file($projectDir . '/config/packages/doctrine.yaml');
            ca()->set($yamlConfigCacheKey, json_encode($config));
        } else {
            $config = json_decode($config, true);
        }

        $entityManagers = $config['doctrine']['orm']['entity_managers'];

        // get full namespace of static class
        $namespace = static::class;
        // remove class name to leave only namespace
        $namespace = substr($namespace, 0, strrpos($namespace, '\\'));

        foreach ($entityManagers as $connection => $entityManager) {
            foreach ($entityManager['mappings'] as $mapping) {
                if ($mapping['prefix'] === $namespace) {
                    self::$repositories[static::class] = em($connection)->getRepository(static::class);

                    return;
                }
            }
        }

        throw new InvalidConfigurationException('Entity repository not found');
    }
}
