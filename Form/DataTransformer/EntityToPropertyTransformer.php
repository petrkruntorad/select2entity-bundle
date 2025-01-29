<?php

namespace Tetranz\Select2EntityBundle\Form\DataTransformer;

use Doctrine\ORM\UnexpectedResultException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for single mode (i.e., multiple = false)
 *
 * Class EntityToPropertyTransformer
 *
 * @package Tetranz\Select2EntityBundle\Form\DataTransformer
 */
class EntityToPropertyTransformer implements DataTransformerInterface
{

    /**
     * @param ObjectManager $em
     * @param string $className
     * @param string|null $textProperty
     * @param string $primaryKey
     * @param string $newTagPrefix
     * @param string $newTagText
     * @param PropertyAccessor $accessor
     */
    public function __construct(
        private readonly ObjectManager $em,
        private string $className,
        private readonly ?string $textProperty = null,
        private readonly string $primaryKey = 'id',
        private readonly string $newTagPrefix = '__',
        private readonly string $newTagText = ' (NEW)',
        private PropertyAccessor $accessor,
    )
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform entity to array
     *
     * @param mixed $value
     * @return array
     */
    public function transform(mixed $value): array
    {
        $data = array();
        if (empty($value)) {
            return $data;
        }

        $text = is_null($this->textProperty)
            ? (string) $value
            : $this->accessor->getValue($value, $this->textProperty);

        if ($this->em->contains($value)) {
            $v = (string) $this->accessor->getValue($value, $this->primaryKey);
        } else {
            $v = $this->newTagPrefix . $text;
            $text = $text.$this->newTagText;
        }

        $data[$v] = $text;

        return $data;
    }

    /**
     * Transform single id value to an entity
     *
     * @param string $value
     * @return mixed|null|object
     */
    public function reverseTransform($value): mixed
    {
        if (empty($value)) {
            return null;
        }

        // Add a potential new tag entry
        $tagPrefixLength = strlen($this->newTagPrefix);
        $cleanValue = substr($value, $tagPrefixLength);
        $valuePrefix = substr($value, 0, $tagPrefixLength);
        if ($valuePrefix == $this->newTagPrefix) {
            // In that case, we have a new entry
            $entity = new $this->className;
            $this->accessor->setValue($entity, $this->textProperty, $cleanValue);
        } else {
            // We do not search for a new entry, as it does not exist yet, by definition
            try {
                $entity = $this->em->createQueryBuilder()
                    ->select('entity')
                    ->from($this->className, 'entity')
                    ->where('entity.'.$this->primaryKey.' = :id')
                    ->setParameter('id', $value)
                    ->getQuery()
                    ->getSingleResult();
            }
            catch (UnexpectedResultException $ex) {
                // this will happen if the form submits invalid data
                throw new TransformationFailedException(sprintf('The choice "%s" does not exist or is not unique', $value));
            }
        }

        if (!$entity) {
            return null;
        }

        return $entity;
    }
}
