<?php

namespace Tetranz\Select2EntityBundle\Form\DataTransformer;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for multiple mode (i.e., multiple = true)
 *
 * Class EntitiesToPropertyTransformer
 * @package Tetranz\Select2EntityBundle\Form\DataTransformer
 */
class EntitiesToPropertyTransformer implements DataTransformerInterface
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
        private string                 $className,
        private readonly ?string       $textProperty = null,
        private readonly string        $primaryKey = 'id',
        private readonly string        $newTagPrefix = '__',
        private readonly string $newTagText = ' (NEW)',
        private PropertyAccessor $accessor,
    )
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform initial entities to array
     *
     * @param mixed $value
     * @return array
     */
    public function transform(mixed $value): array
    {
        if (empty($value)) {
            return array();
        }

        $data = array();

        foreach ($value as $entity) {
            $text = is_null($this->textProperty)
                ? (string) $entity
                : $this->accessor->getValue($entity, $this->textProperty);

            if ($this->em->contains($entity)) {
                $v = (string) $this->accessor->getValue($entity, $this->primaryKey);
            } else {
                $v = $this->newTagPrefix . $text;
                $text = $text.$this->newTagText;
            }

            $data[$v] = $text;
        }

        return $data;
    }

    /**
     * Transform array to a collection of entities
     *
     * @param mixed $value
     * @return array
     */
    public function reverseTransform(mixed $value): array
    {
        if (!is_array($value) || empty($value)) {
            return array();
        }

        // add new tag entries
        $newObjects = array();
        $tagPrefixLength = strlen($this->newTagPrefix);
        foreach ($value as $key => $v) {
            $cleanValue = substr($v, $tagPrefixLength);
            $valuePrefix = substr($v, 0, $tagPrefixLength);
            if ($valuePrefix == $this->newTagPrefix) {
                $object = new $this->className;
                $this->accessor->setValue($object, $this->textProperty, $cleanValue);
                $newObjects[] = $object;
                unset($value[$key]);
            }
        }

        // get multiple entities with one query
        $entities = $this->em->createQueryBuilder()
            ->select('entity')
            ->from($this->className, 'entity')
            ->where('entity.'.$this->primaryKey.' IN (:ids)')
            ->setParameter('ids', $value)
            ->getQuery()
            ->getResult();

        // this will happen if the form submits invalid data
        if (count($entities) != count($value)) {
            throw new TransformationFailedException('One or more id values are invalid');
        }

        return array_merge($entities, $newObjects);
    }
}
