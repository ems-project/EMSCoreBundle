<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\NotificationFilter;
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
            ->andWhere('r.deleted = :false')
            ->andWhere('n.status = :status')
            ->andWhere('n.environment = :environment');

        $qb->setParameters(new ArrayCollection([
            new Parameter('status', 'pending'),
            new Parameter('contentType', $revision->getContentType()),
            new Parameter('ouuid', $revision->getOuuid()),
            new Parameter('environment', $environment),
            new Parameter('false', false),
        ]));

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function countRejectedForUser(UserInterface $user): int
    {
        $query = $this->createQueryBuilder('n')
            ->select('COUNT(n)')
            ->where('n.status = :status')
            ->andWhere('n.username =  :username')
            ->setParameters(new ArrayCollection([
                new Parameter('status', 'rejected'),
                new Parameter('username', $user->getUsername()),
            ]));

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * @param ContentType[] $contentTypes
     * @param Environment[] $environments
     * @param Template[]    $templates
     */
    public function countPendingByUserRoleAndCircle(UserInterface $user, ?array $contentTypes = null, ?array $environments = null, ?array $templates = null): int
    {
        $templateIds = $this->getTemplatesIdsForUser($user, $contentTypes ?? []);

        $query = $this->createQueryBuilder('n')
        ->select('COUNT(n)')
        ->where('n.status = :status')
        ->andWhere('n.template IN (:ids)');
        $params = new ArrayCollection([
            new Parameter('status', 'pending'),
            new Parameter('ids', $templateIds, ArrayParameterType::INTEGER),
        ]);

        if (null != $environments) {
            $query->andWhere('n.environment IN (:envs)');
            $params->add(new Parameter('envs', $environments));
        }
        if (null != $templates) {
            $query->andWhere('n.template IN (:templates)');
            $params->add(new Parameter('templates', $templates));
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
        ->andWhere('r.ouuid = :ouuid');

        $qb->setParameters(new ArrayCollection([
            new Parameter('status', 'pending'),
            new Parameter('contentType', $contentType),
            new Parameter('ouuid', $ouuid),
        ]));

        $query = $qb->getQuery();

        $results = $query->getResult();

        return (int) $results[0][1];
    }

    /**
     * @return Notification[]
     */
    public function findByPendingAndRoleAndCircleForUserSent(UserInterface $user, int $from, int $limit, NotificationFilter $notificationFilter): array
    {
        $qb = $this->getQueryBuilderForSent($user, $notificationFilter->contentType->toArray());
        $qb->select('n')->setFirstResult($from)->setMaxResults($limit);

        if (\count($notificationFilter->environment) > 0) {
            $qb->andWhere('n.environment IN (:envs)')->setParameter('envs', $notificationFilter->environment);
        }

        if ($notificationFilter->template->count() > 0) {
            $qb->andWhere('n.template IN (:templates)')->setParameter('templates', $notificationFilter->template->toArray());
        }

        return $qb->getQuery()->getResult();
    }

    public function countForSent(UserInterface $user): int
    {
        $qb = $this->getQueryBuilderForSent($user);

        return (int) $qb->select('count(n.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @param ContentType[] $contentTypes
     */
    private function getQueryBuilderForSent(UserInterface $user, array $contentTypes = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n');
        $qb
            ->join('n.revision', 'r', 'WITH', 'n.revision = r.id')
            ->join('n.environment', 'e', 'WITH', 'n.environment = e.id')
            ->andWhere('n.status = :status')
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->andWhere('r.id = n.revision')
            ->setParameter('status', 'pending');

        $templateIds = $this->getTemplatesIdsForUserFrom($user, $contentTypes);

        if (\count($templateIds) > 0) {
            $qb->andWhere('n.template IN (:ids)')->setParameter('ids', $templateIds);
        }

        $orCircles = $qb->expr()->orX();
        $orCircles->add('r.circles is null');

        $counter = 0;
        foreach ($user->getCircles() as $circle) {
            $orCircles->add('r.circles like :circle_'.$counter);
            $qb->setParameter('circle_'.$counter, '%'.$circle.'%');
            ++$counter;
        }

        $qb->andWhere($orCircles);

        return $qb;
    }

    /**
     * @param int[] $contentTypes
     * @param int[] $environments
     * @param int[] $templates
     *
     * @return Notification[]
     */
    public function findRejectedForUser(UserInterface $user, int $from, int $limit, ?array $contentTypes = null, ?array $environments = null, ?array $templates = null): array
    {
        $qb = $this->createQueryBuilder('n')
        ->select('n')
        ->where('n.status = :status')
        ->andWhere('n.username = :username');
        $params = new ArrayCollection([
            new Parameter('status', 'rejected'),
            new Parameter('username', $user->getUsername()),
        ]);

        if (null != $environments) {
            $qb->andWhere('n.environment IN (:envs)');
            $params->add(new Parameter('envs', $environments));
        }
        if (null != $templates) {
            $qb->andWhere('n.template IN (:templates)');
            $params->add(new Parameter('templates', $templates));
        }

        $qb->setParameters($params)
            ->setFirstResult($from)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param ContentType[] $contentTypes
     * @param Environment[] $environments
     * @param Template[]    $templates
     *
     * @return Notification[]
     */
    public function findByPendingAndUserRoleAndCircle(UserInterface $user, int $from, int $limit, ?array $contentTypes = null, ?array $environments = null, ?array $templates = null): array
    {
        $templateIds = $this->getTemplatesIdsForUser($user, $contentTypes ?? []);

        $qb = $this->createQueryBuilder('n')
        ->select('n')
        ->where('n.status = :status')
        ->andWhere('n.template IN (:ids)');
        $params = new ArrayCollection([
            new Parameter('status', 'pending'),
            new Parameter('ids', $templateIds),
        ]);

        if (null != $environments) {
            $qb->andWhere('n.environment IN (:envs)');
            $params->add(new Parameter('envs', $environments));
        }
        if (null != $templates) {
            $qb->andWhere('n.template IN (:templates)');
            $params->add(new Parameter('templates', $templates));
        }

        $qb->setParameters($params)
            ->setFirstResult($from)
            ->setMaxResults($limit);
        $query = $qb->getQuery();

        $results = $query->getResult();

        return $results;
    }

    /**
     * @param ContentType[] $contentTypes
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
     * @param ContentType[] $contentTypes
     *
     * @return int[]
     */
    private function getTemplatesIdsForUserFrom(UserInterface $user, ?array $contentTypes = null): array
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
           ->andWhere($query->expr()->lte('n.emailed', ':datePivot'))
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
            ->andWhere('n.responseEmailed is NULL')
            ->setParameters(new ArrayCollection([
                new Parameter('status', 'pending'),
            ]));

        return $query->getQuery()->getResult();
    }
}
