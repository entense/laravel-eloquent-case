<?php

namespace AgliPanci\LaravelCase\Tests;

use AgliPanci\LaravelCase\Exceptions\InvalidCaseBuilderException;
use AgliPanci\LaravelCase\Facades\CaseBuilder;
use AgliPanci\LaravelCase\Query\CaseBuilder as QueryCaseBuilder;
use Throwable;

class CaseBuilderTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testCanGenerateSimpleQuery()
    {
        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status', 1)
            ->then('Paid')
            ->else('Due');

        $this->assertCount(1, $caseQuery->whens);
        $this->assertCount(1, $caseQuery->thens);
        $this->assertSameSize($caseQuery->whens, $caseQuery->thens);

        $expected_sql = 'case when `payment_status` = ? then ? else ? end';
        $expected_bindings = [ 1, "Paid", "Due", ];
        $expected_compiled_query = 'case when `payment_status` = 1 then "Paid" else "Due" end';

        $this->assertEquals($expected_sql, $caseQuery->toSql());
        $this->assertEquals($expected_bindings, $caseQuery->getBindings());
        $this->assertCount(count($expected_bindings), $caseQuery->getBindings());
        $this->assertEquals($expected_compiled_query, $caseQuery->toRaw());
    }

    /**
     * @throws Throwable
     */
    public function testCanGenerateComplexQuery()
    {
        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status', 1)
            ->then('Paid')
            ->when('payment_status', 2)
            ->then('Due')
            ->when('payment_status', '<=', 5)
            ->then('Canceled')
            ->else('Unknown');


        $this->assertCount(3, $caseQuery->whens);
        $this->assertCount(3, $caseQuery->thens);
        $this->assertSameSize($caseQuery->whens, $caseQuery->thens);
        $this->assertNotEmpty($caseQuery->else);

        $expected_sql = 'case when `payment_status` = ? then ? when `payment_status` = ? then ? when `payment_status` <= ? then ? else ? end';
        $expected_bindings = [ 1, "Paid", 2, "Due", 5, "Canceled", "Unknown" ];
        $expected_compiled_query = 'case when `payment_status` = 1 then "Paid" when `payment_status` = 2 then "Due" when `payment_status` <= 5 then "Canceled" else "Unknown" end';

        $this->assertEquals($expected_sql, $caseQuery->toSql());
        $this->assertEquals($expected_bindings, $caseQuery->getBindings());
        $this->assertCount(count($expected_bindings), $caseQuery->getBindings());
        $this->assertEquals($expected_compiled_query, $caseQuery->toRaw());
    }

    public function testCanUseRawQueries()
    {
        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::whenRaw('payment_status IN (1,2,3)')
            ->thenRaw('Paid')
            ->whenRaw('payment_status >= 4')
            ->then('Due')
            ->else('Unknown');


        $this->assertCount(2, $caseQuery->whens);
        $this->assertCount(2, $caseQuery->thens);
        $this->assertSameSize($caseQuery->whens, $caseQuery->thens);
        $this->assertNotEmpty($caseQuery->else);

        $expected_sql = 'case when payment_status IN (1,2,3) then Paid when payment_status >= 4 then ? else ? end';
        $expected_bindings = [ "Due", "Unknown" ];
        $expected_compiled_query = 'case when payment_status IN (1,2,3) then Paid when payment_status >= 4 then "Due" else "Unknown" end';

        $this->assertEquals($expected_sql, $caseQuery->toSql());
        $this->assertEquals($expected_bindings, $caseQuery->getBindings());
        $this->assertCount(count($expected_bindings), $caseQuery->getBindings());
        $this->assertEquals($expected_compiled_query, $caseQuery->toRaw());
    }

    public function testThrowsElseIsPresent()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('ELSE statement is already present. The CASE statement can have only one ELSE.');

        CaseBuilder::when('payment_status', 1)
            ->then('Paid')
            ->else('Due')
            ->else('Unknown');
    }

    public function testThrowsNoConditionsPresent()
    {
        $this->assertTrue(true);
    }

    public function testThrowsNumberOfConditionsNotMatching()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('The CASE statement must have a matching number of WHEN/THEN conditions.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status', 1);
        $caseQuery->toSql();
    }

    public function testThrowsSubjectMustBePresentWhenCaseOperatorNotUsed()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('The CASE statement subject must be present when operator and column are not present.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status')
            ->then('Paid');
        $caseQuery->toSql();
    }

    public function testThrowsThenCannotBeBeforeWhen()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('THEN cannot be before WHEN on a CASE statement.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::then('Paid')
            ->when('payment_status', 1);
        $caseQuery->toSql();
    }

    public function testThrowsElseCanOnlyBeAfterAWhenThen()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('ELSE can only be set after a WHEN/THEN in a CASE statement.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::else('Unknown')
            ->when('payment_status', 1)
            ->then('Due');
        $caseQuery->toRaw();
    }

    public function testThrowsElseCanOnlyBeAfterAWhenThenMiddle()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('ELSE can only be set after a WHEN/THEN in a CASE statement.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status', 1)
            ->else('Unknown')
            ->then('Due');
        $caseQuery->toRaw();
    }

    public function testThrowsWrongWhenPosition()
    {
        $this->expectException(InvalidCaseBuilderException::class);
        $this->expectExceptionMessage('Wrong WHEN position.');

        /**
         * @var QueryCaseBuilder $caseQuery
         */
        $caseQuery = CaseBuilder::when('payment_status', 1)
            ->then('Paid')
            ->when('payment_status', 2)
            ->when('payment_status', 3);
        $caseQuery->toSql();
    }
}