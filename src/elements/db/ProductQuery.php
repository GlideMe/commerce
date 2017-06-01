<?php

namespace craft\commerce\elements\db;

use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use Craft;

/**
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class ProductQuery extends ElementQuery
{
    /**
     * @var bool Whether to only return products that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var ProductType The product type the resulting products must belong to.
     */
    public $type;

    /**
     * @var int|int[]|null The product type ID(s) that the resulting products must have.
     */
    public $typeId;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $postDate;


    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $expiryDate;

    /**
     * @var mixed The Post Date that the resulting products must be after.
     */
    public $after;

    /**
     * @var mixed The Post Date that the resulting products must be before.
     */
    public $before;

    /**
     * @var float The default price the resulting products must have.
     */
    public $defaultPrice;

    /**
     * @var float The default height the resulting products must have.
     */
    public $defaultHeight;

    /**
     * @var float The default length the resulting products must have.
     */
    public $defaultLength;

    /**
     * @var float The default width the resulting products must have.
     */
    public $defaultWidth;

    /**
     * @var float The default weight the resulting products must have.
     */
    public $defaultWeight;

    /**
     * @var VariantQuery only return products that match the resulting variant query.
     */
    public $hasVariant;

    /**
     * @var bool The sale status the resulting products should have.
     */
    public $hasSales;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'live';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'type':
                $this->type($value);
                break;
            case 'before':
                $this->before($value);
                break;
            case 'after':
                $this->after($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[typeId]] property based on a given product types(s)’s handle(s).
     *
     * @param string|string[]|ProductType|null $value The property value
     *
     * @return static self reference
     */
    public function type($value)
    {
        if ($value instanceof ProductType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_producttypes}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<'.$value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>='.$value;

        return $this;
    }

    /**
     * Sets the [[editable]] property.
     *
     * @param bool $value The property value (defaults to true)
     *
     * @return static self reference
     */
    public function editable(bool $value = true)
    {
        $this->editable = $value;

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return static self reference
     */
    public function postDate($value)
    {
        $this->postDate = $value;

        return $this;
    }

    /**
     * Sets the [[expiryDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return static self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('commerce_products');

        $this->query->select([
            'products.id',
            'products.typeId',
            'products.promotable',
            'products.freeShipping',
            'products.postDate',
            'products.expiryDate',
            'products.defaultPrice',
            'products.defaultVariantId',
            'products.defaultSku',
            'products.defaultWeight',
            'products.defaultLength',
            'products.defaultWidth',
            'products.defaultHeight',
            'products.taxCategoryId',
            'products.shippingCategoryId'
        ]);

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.expiryDate', $this->expiryDate));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('entries.typeId', $this->typeId));
        }

        if ($this->defaultPrice) {
            $this->subQuery->andWhere(Db::parseParam('products.defaultPrice', $this->defaultPrice));
        }

        if ($this->defaultHeight) {
            $this->subQuery->andWhere(Db::parseParam('products.defaultHeight', $this->defaultHeight));
        }

        if ($this->defaultLength) {
            $this->subQuery->andWhere(Db::parseParam('products.defaultLength', $this->defaultLength));
        }

        if ($this->defaultWidth) {
            $this->subQuery->andWhere(Db::parseParam('products.defaultWidth', $this->defaultWidth));
        }

        if ($this->defaultWeight) {
            $this->subQuery->andWhere(Db::parseParam('products.defaultWeight', $this->defaultWeight));
        }

        $this->_applyEditableParam();
        $this->_applyRefParam();
        $this->_applyHasSalesParam();


        if (!$this->orderBy) {
            $this->orderBy = 'postDate desc';
        }

        return parent::beforePrepare();
    }

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @return void
     * @throws QueryAbortedException
     */
    private function _applyEditableParam()
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            'products.typeId' => Plugin::getInstance()->getProductTypes()->getEditableProductTypeIds()
        ]);
    }

    // Private Methods
    // =========================================================================


    /**
     * Applies the 'ref' param to the query being prepared.
     *
     * @return void
     */
    private function _applyRefParam()
    {
        if (!$this->ref) {
            return;
        }

        $refs = ArrayHelper::toArray($this->ref);
        $joinSections = false;
        $condition = ['or'];

        foreach ($refs as $ref) {
            $parts = array_filter(explode('/', $ref));

            if (!empty($parts)) {
                if (count($parts) == 1) {
                    $condition[] = Db::parseParam('elements_i18n.slug', $parts[0]);
                } else {
                    $condition[] = [
                        'and',
                        Db::parseParam('commerce_producttypes.handle', $parts[0]),
                        Db::parseParam('elements_i18n.slug', $parts[1])
                    ];
                    $joinSections = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinSections) {
            $this->subQuery->innerJoin('{{%commerce_producttypes}} producttypes', '[[producttypes.id]] = [[products.typeId]]');
        }
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Entry::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1'
                    ],
                    ['<=', 'entries.postDate', $currentTimeDb],
                    [
                        'or',
                        ['entries.expiryDate' => null],
                        ['>', 'entries.expiryDate', $currentTimeDb]
                    ]
                ];
            case Entry::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1',
                    ],
                    ['>', 'entries.postDate', $currentTimeDb]
                ];
            case Entry::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1'
                    ],
                    ['not', ['entries.expiryDate' => null]],
                    ['<=', 'entries.expiryDate', $currentTimeDb]
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    private function _applyHasSalesParam()
    {
        if (null !== $this->hasSales) {
            $productsQuery = Product::find();
            $productsQuery->hasSales = null;
            $productsQuery->limit = null;
            /** @var Product[] $products */
            $products = $productsQuery->all();

            $productIds = [];
            foreach ($products as $product) {
                $sales = Plugin::getInstance()->getSales()->getSalesForProduct($product);

                if ($this->hasSales === true && count($sales) > 0) {
                    $productIds[] = $product->id;
                }

                if ($this->hasSales === false && count($sales) == 0) {
                    $productIds[] = $product->id;
                }
            }

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);

            $this->subQuery->andWhere(['in', 'products.id', $productIds]);
        }
    }
}