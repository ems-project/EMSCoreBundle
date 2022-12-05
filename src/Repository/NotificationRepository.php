<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry, private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findByRevisionOuuidAndEnvironment(Revision $revision, Environment $environment): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select('n')
            ->join('n.revision', 'r', 'WITH', 'n.revision = r.id')
            ->where('r.ouuid = :ouuid')
            ->andWhere('r.contentType = :contentType')
            ->andwhere('r.deleted = :false')
            ->andwhere('n.status = :status')
            ->andwhere('n.environment = :environment');

        $qb->setParameters([
                'status' => 'pending',
                'contentType' => $revision->getContentType(),
                'ouuid' => $revision->getOuuid(),
                'environment' => $environment,
                'false' => false,
        ]);

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function countRejectedForUser(UserInterface $user): int
    {
        $query = $this->createQueryBuilder('n')
        ->select('COUNT(n)')
        ->where('n.status = :status')
        ->andwhere('n.username =  :username');
        $params = ['status' => 'rejected', 'username' => $user->getUsername()];

        $query->setParameters($params);

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int[] $contentTypes
     * @param int[] $environments
     * @param int[] $templates
     */
    public function countPendingByUserRoleAndCircle(UserInterface $user, array $contentTypes = null, array $environments = null, array $templates = null): int
    {
        $templateIds = $this->getTemplatesIdsForUser($user, $contentTypes ?? []);

        $query = $this->createQueryBuilder('n')
        ->select('COUNT(n)')
        ->where('n.status = :status')
        ->andwhere('n.template IN (:ids)');
        $params = ['status' => 'pending', 'ids' => $templateIds];

        if (null != $environments) {
            $query->andWhere('n.environment IN (:envs)');
            $params['envs'] = $environments;
        }
        if (null != $templates) {
            $query->andWhere('n.template IN (:templates)');
            $params['templates'] = $templates;
        }

        $query->setParameters($params);

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    public function countNotificationByUuidAndContentType(string $ouuid, ContentType $contentType): int
    {
        $qb = $this->createQueryBuilder('n')
        ->select('count(n)')
        ->join('n.revision', 'r', 'WITH', 'n.revision = r.id')
        ->where('n.status = :status')
        ->andWhere('r.contentType = :contentType')
        ->andwhere('r.ouuid = :ouuid');

        $qb->setParameters([
                'status' => 'pending',
                'contentType' => $contentType,
                'ouuid' => $ouuid,
        ]);

        $query = $qb->getQuery();

        $results = $query->getResult();

        return (int) $results[0][1];
    }

    /**
     * @param int[] $contentTypes
     * @param int[] $environments
     * @param int[] $templates
     *
     * @return Notification[]
     */
    public function findByPendingAndRoleAndCircleForUserSent(UserInterface $user, int $from, int $limit, array $contentTypes = null, array $environments = null, array $templates = null): array
    {
        $templateIds = $this->getTemplatesIdsForUserFrom($user, $contentTypes ?? []);

        $qb = $this->createQueryBuilder('n')
            ->select('n')
            ->join('n.revision', 'r', 'WITH', 'n.revision = r.id')
            ->join('n.environment', 'e', 'WITH', 'n.environment = e.id')
            ->where('n.status = :status')
            ->andwhere('n.template IN (:ids)')
            ->andwhere('r.deleted = :false')
            ->andwhere('r.id = n.revision');

        $params = [
                'status' => 'pending',
                'ids' => $templateIds,
                'false' => false,
            ];

        if (null != $environments) {
            $qb->andWhere('n.environment IN (:envs)');
            $params['envs'] = $environments;
        }
        if (null != $templates) {
            $qb->andWhere('n.template IN (:templates)');
            $params['templates'] = $templates;
        }

        $orCircles = $qb->expr()->orX();
        $orCircles->add('r.circles is null');

        $counter = 0;
        foreach ($user->getCircles() as $circle) {
            $orCircles->add('r.circles like :circle_'.$counter);
            $params['circle_'.$counter] = '%'.$circle.'%';
            ++$counter;
        }

        $qb->andWhere($orCircles);

        $qb->setParameters($params)
            ->setFirstResult($from)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countForSent(UserInterface $user): int
    {
        $templateIds = $this->getTemplatesIdsForUserFrom($user);

        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n)')
            ->join('n.revision', 'r', 'WITH', 'n.revision = r.id')
            ->join('n.environment', 'e', 'WITH', 'n.environment = e.id')
            ->where('n.status = :status')
            ->andwhere('r.deleted = :false')
            ->andwhere('r.id = n.revision');

        $params = [
                'status' => 'pending',
                'false' => false,
        ];

        if (!empty($templateIds)) {
            $qb->andwhere('n.template IN (:ids)');
            $params['ids'] = $templateIds;
        }

        $orCircles = $qb->expr()->orX();
        $orCircles->add('r.circles is null');

        $counter = 0;
        foreach ($user->getCircles() as $circle) {
            $orCircles->add('r.circles like :circle_'.$counter);
            $params['circle_'.$counter] = '%'.$circle.'%';
            ++$counter;
        }

        $qb->andWhere($orCircles);

        $qb->setParameters($params);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int[] $contentTypes
     * @param int[] $environments
     * @param int[] $templates
     *
     * @return Notification[]
     */
    public function findRejectedForUser(UserInterface $user, int $from, int $limit, array $contentTypes = null, array $environments = null, array $templates = null): array
    {
        $qb = $this->createQueryBuilder('n')
        ->select('n')
        ->where('n.status = :status')
        ->andwhere('n.username = :username');
        $params = ['status' => 'rejected', 'username' => $user->getUsername()];

        if (null != $environments) {
            $qb->andWhere('n.environment IN (:envs)');
            $params['envs'] = $environments;
        }
        if (null != $templates) {
            $qb->andWhere('n.template IN (:templates)');
            $params['templates'] = $templates;
        }

        $qb->setParameters($params)
            ->setFirstResult($from)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $contentTypes
     * @param int[] $environments
     * @param int[] $templates
     *
     * @return Notification[]
     */
    public function findByPendingAndUserRoleAndCircle(UserInterface $user, int $from, int $limit, array $contentTypes = null, array $environments = null, array $templates = null): array
    {
        $templateIds = $this->getTemplatesIdsForUser($user, $contentTypes ?? []);

        $qb = $this->createQueryBuilder('n')
        ->select('n')
        ->where('n.status = :status')
        ->andwhere('n.template IN (:ids)');
        $params = ['status' => 'pending', 'ids' => $templateIds];

        if (null != $environments) {
            $qb->andWhere('n.environment IN (:envs)');
            $params['envs'] = $environments;
        }
        if (null != $templates) {
            $qb->andWhere('n.template IN (:templates)');
            $params['templates'] = $templates;
        }

        $qb->setParameters($params)
            ->setFirstResult($from)
            ->setMaxResults($limit);
        $query = $qb->getQuery();

        $results = $query->getResult();

        return $results;
    }

    /**
     * @param int[] $contentTypes
     *
     * @return int[]
     */
    private function getTemplatesIdsForUser(UserInterface $user, array $contentTypes): array
    {
        $circles = $user->getCircles();

        $em = $this->getEntityManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository(Template::class);

        $results = $templateRepository->findByRenderOptionAndContentType('notification', $contentTypes);

        $templateIds = [];
        foreach ($results as $template) {
            $role = $template->getRoleTo();
            if ($this->authorizationChecker->isGranted($role) || 'not-defined' === $role) {
                if (empty($template->getCirclesTo())) {
                    $templateIds[] = $template->getId();
                } else {
                    $commonCircle = \array_intersect($circles, $template->getCirclesTo());
                    if (!empty($commonCircle) || $this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
                        $templateIds[] = $template->getId();
                    }
                }
            }
        }

        return $templateIds;
    }

    /**
     * @param int[] $contentTypes
     *
     * @return int[]
     */
    private function getTemplatesIdsForUserFrom(UserInterface $user, array $contentTypes = null): array
    {
        $em = $this->getEntityManager();

        /** @var TemplateRepository $templateRepoitory */
        $templateRepoitory = $em->getRepository(Template::class);

        $results = $templateRepoitory->findByRenderOptionAndContentType('notification', $contentTypes);

        $templateIds = [];
        foreach ($results as $template) {
            foreach ($template->getEnvironments() as $environment) {
                if (empty($environment->getCircles()) || \count(\array_intersect($environment->getCircles(), $user->getCircles())) > 0) {
                    $templateIds[] = $template->getId();
                    break;
                }
            }
        }

        return $templateIds;
    }

    /**
     * @return Notification[]
     */
    public function findReminders(\DateTime $date): array
    {
        $query = $this->createQueryBuilder('n');

        $query->select('n')
           ->where('n.status = :status')
           ->andwhere($query->expr()->lte('n.emailed', ':datePivot'))
            ->setParameter('status', 'pending')
            ->setParameter('datePivot', $date);

        return $query->getQuery()->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findResponses(): array
    {
        $query = $this->createQueryBuilder('n')
           ->select('n')
           ->where('n.status <> :status')
           ->andwhere('n.responseEmailed is NULL')
            ->setParameters([
                    'status' => 'pending',
            ]);

        return $query->getQuery()->getResult();
    }
}
