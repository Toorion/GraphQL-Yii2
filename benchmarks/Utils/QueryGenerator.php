<?php
namespace YiiGraphQL\Benchmarks\Utils;

use YiiGraphQL\Language\AST\DocumentNode;
use YiiGraphQL\Language\AST\FieldNode;
use YiiGraphQL\Language\AST\NameNode;
use YiiGraphQL\Language\AST\OperationDefinitionNode;
use YiiGraphQL\Language\AST\SelectionSetNode;
use YiiGraphQL\Language\Printer;
use YiiGraphQL\Type\Definition\FieldDefinition;
use YiiGraphQL\Type\Definition\InterfaceType;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\WrappingType;
use YiiGraphQL\Type\Schema;
use YiiGraphQL\Utils\Utils;
use function count;
use function max;
use function round;

class QueryGenerator
{
    private $schema;

    private $maxLeafFields;

    private $currentLeafFields;

    public function __construct(Schema $schema, $percentOfLeafFields)
    {
        $this->schema = $schema;

        Utils::invariant(0 < $percentOfLeafFields && $percentOfLeafFields <= 1);

        $totalFields = 0;
        foreach ($schema->getTypeMap() as $type) {
            if (! ($type instanceof ObjectType)) {
                continue;
            }

            $totalFields += count($type->getFields());
        }

        $this->maxLeafFields     = max(1, round($totalFields * $percentOfLeafFields));
        $this->currentLeafFields = 0;
    }

    public function buildQuery()
    {
        $qtype = $this->schema->getQueryType();

        $ast = new DocumentNode([
            'definitions' => [new OperationDefinitionNode([
                'name' => new NameNode(['value' => 'TestQuery']),
                'operation' => 'query',
                'selectionSet' => $this->buildSelectionSet($qtype->getFields()),
            ]),
            ],
        ]);

        return Printer::doPrint($ast);
    }

    /**
     * @param FieldDefinition[] $fields
     *
     * @return SelectionSetNode
     */
    public function buildSelectionSet($fields)
    {
        $selections[] = new FieldNode([
            'name' => new NameNode(['value' => '__typename']),
        ]);
        $this->currentLeafFields++;

        foreach ($fields as $field) {
            if ($this->currentLeafFields >= $this->maxLeafFields) {
                break;
            }

            $type = $field->getType();

            if ($type instanceof WrappingType) {
                $type = $type->getWrappedType(true);
            }

            if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                $selectionSet = $this->buildSelectionSet($type->getFields());
            } else {
                $selectionSet = null;
                $this->currentLeafFields++;
            }

            $selections[] = new FieldNode([
                'name' => new NameNode(['value' => $field->name]),
                'selectionSet' => $selectionSet,
            ]);
        }

        $selectionSet = new SelectionSetNode([
            'selections' => $selections,
        ]);

        return $selectionSet;
    }
}
