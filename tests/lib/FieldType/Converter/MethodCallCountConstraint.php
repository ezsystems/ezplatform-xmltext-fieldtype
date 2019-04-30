<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Converter;

use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Class MethodCallCountConstraint.
 *
 * When generating mocks in PHPUnit, you cannot validate that the mock methods are called the correct
 * number of times.
 *
 * However, with this constrain you may.
 * To ensure foobarMethod() is not called more than twice:
 * $mock->expects(new MethodCallCountConstraint(2))->method('foobarMethod);
 *
 * Based on workaround proposed on https://github.com/sebastianbergmann/phpunit-mock-objects/issues/65
 */
class MethodCallCountConstraint extends InvokedRecorder
{
    /**
     * @var int
     */
    protected $expectedCount;

    /**
     * @param int $expectedCount
     */
    public function __construct($expectedCount)
    {
        $this->expectedCount = $expectedCount;
    }

    /**
     * @param  PHPUnit_Framework_MockObject_Invocation $invocation
     * @return mixed|void
     * @throws PHPUnit_Framework_ExpectationFailedException
     */
    public function invoked(Invocation $invocation)
    {
        parent::invoked($invocation);

        $count = $this->getInvocationCount();

        if ($count > $this->expectedCount) {
            $message = 'Call to ' . $invocation->toString() . ' unexpected';

            throw new ExpectationFailedException($message);
        }
    }

    /**
     * Returns a string representation of the object.
     *
     * @return string
     */
    public function toString()
    {
        return 'MethodCallCountConstraint';
    }

    /**
     * Verifies that the current expectation is valid. If everything is OK the
     * code should just return, if not it must throw an exception.
     *
     * @throws PHPUnit_Framework_ExpectationFailedException
     */
    public function verify($test = null)
    {
        $count = $this->getInvocationCount();
        if ($count != $this->expectedCount) {
            throw new ExpectationFailedException(
                sprintf(
                    'Methods of class was expected to be called %d times, ' .
                    'actually called %d times.' . ($test ? "\nWhile testing '%s'." : ''),

                    $this->expectedCount,
                    $count,
                    $test
                )
            );
        }
    }
}
