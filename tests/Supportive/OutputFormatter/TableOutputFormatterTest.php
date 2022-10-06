<?php

declare(strict_types=1);

namespace Tests\Qossmic\Deptrac\Supportive\OutputFormatter;

use PHPUnit\Framework\TestCase;
use Qossmic\Deptrac\Contract\Ast\DependencyType;
use Qossmic\Deptrac\Contract\Ast\FileOccurrence;
use Qossmic\Deptrac\Contract\OutputFormatter\OutputFormatterInput;
use Qossmic\Deptrac\Contract\Result\Error;
use Qossmic\Deptrac\Contract\Result\LegacyResult;
use Qossmic\Deptrac\Contract\Result\SkippedViolation;
use Qossmic\Deptrac\Contract\Result\Uncovered;
use Qossmic\Deptrac\Contract\Result\Violation;
use Qossmic\Deptrac\Contract\Result\Warning;
use Qossmic\Deptrac\Core\Ast\AstMap\AstInherit;
use Qossmic\Deptrac\Core\Ast\AstMap\AstInheritType;
use Qossmic\Deptrac\Core\Ast\AstMap\ClassLike\ClassLikeToken;
use Qossmic\Deptrac\Core\Dependency\Dependency;
use Qossmic\Deptrac\Core\Dependency\InheritDependency;
use Qossmic\Deptrac\Supportive\Console\Symfony\Style;
use Qossmic\Deptrac\Supportive\Console\Symfony\SymfonyOutput;
use Qossmic\Deptrac\Supportive\OutputFormatter\TableOutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class TableOutputFormatterTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('table', (new TableOutputFormatter())->getName());
    }

    public function basicDataProvider(): iterable
    {
        $originalA = ClassLikeToken::fromFQCN('OriginalA');
        $originalB = ClassLikeToken::fromFQCN('OriginalB');

        yield [
            [
                new Violation(
                    new InheritDependency(
                        ClassLikeToken::fromFQCN('ClassA'),
                        ClassLikeToken::fromFQCN('ClassB'),
                        new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                        (new AstInherit(
                            ClassLikeToken::fromFQCN('ClassInheritA'), new FileOccurrence('originalA.php', 3),
                            AstInheritType::EXTENDS
                        ))
                            ->withPath(
                                [
                                    new AstInherit(
                                        ClassLikeToken::fromFQCN('ClassInheritB'),
                                        new FileOccurrence('originalA.php', 4),
                                        AstInheritType::EXTENDS
                                    ),
                                    new AstInherit(
                                        ClassLikeToken::fromFQCN('ClassInheritC'),
                                        new FileOccurrence('originalA.php', 5),
                                        AstInheritType::EXTENDS
                                    ),
                                    new AstInherit(
                                        ClassLikeToken::fromFQCN('ClassInheritD'),
                                        new FileOccurrence('originalA.php', 6),
                                        AstInheritType::EXTENDS
                                    ),
                                ]
                            )
                    ),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            'warnings' => [],
            ' ----------- ------------------------------------------- 
  Reason      LayerA                                     
 ----------- ------------------------------------------- 
  Violation   ClassA must not depend on ClassB (LayerB)  
              ClassInheritD::6 ->                        
              ClassInheritC::5 ->                        
              ClassInheritB::4 ->                        
              ClassInheritA::3 ->                        
              OriginalB::12                              
              originalA.php:12                           
 ----------- ------------------------------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           1    
  Skipped violations   0    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
        ];

        yield [
            [
                new Violation(
                    new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            'warnings' => [],
            ' ----------- ------------------------------------------------- 
  Reason      LayerA                                           
 ----------- ------------------------------------------------- 
  Violation   OriginalA must not depend on OriginalB (LayerB)  
              originalA.php:12                                 
 ----------- ------------------------------------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           1    
  Skipped violations   0    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
        ];

        yield [
            [],
            [],
            'warnings' => [],
            '
 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
        ];

        yield 'skipped violations' => [
            [
                new SkippedViolation(
                    new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            'warnings' => [],
            ' --------- ------------------------------------------------- 
  Reason    LayerA                                           
 --------- ------------------------------------------------- 
  Skipped   OriginalA must not depend on OriginalB (LayerB)  
            originalA.php:12                                 
 --------- ------------------------------------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   1    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
        ];

        yield 'skipped violations without reporting' => [
            [
                new SkippedViolation(
                    new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            'warnings' => [],
            '
 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   1    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
            'reportUncovered' => true,
            'reportSkipped' => false,
        ];

        yield 'uncovered' => [
            'rules' => [
                new Uncovered(
                    new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                    'LayerA'
                ),
            ],
            'errors' => [],
            'warnings' => [],
            'expectedOutput' => ' ----------- ------------------------------------------------- 
  Reason      LayerA                                           
 ----------- ------------------------------------------------- 
  Uncovered   OriginalA has uncovered dependency on OriginalB  
              originalA.php:12                                 
 ----------- ------------------------------------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            1    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
        ];

        yield 'uncovered without reporting' => [
            'rules' => [
                new Uncovered(
                    new Dependency($originalA, $originalB, new FileOccurrence('originalA.php', 12), DependencyType::PARAMETER),
                    'LayerA'
                ),
            ],
            'errors' => [],
            'warnings' => [],
            'expectedOutput' => '
 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            1    
  Allowed              0    
  Warnings             0    
  Errors               0    
 -------------------- ----- 

',
            'reportUncovered' => false,
        ];

        yield 'an error occurred' => [
            [],
            [new Error('an error occurred')],
            'warnings' => [],
            ' ------------------- 
  Errors             
 ------------------- 
  an error occurred  
 ------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            0    
  Allowed              0    
  Warnings             0    
  Errors               1    
 -------------------- ----- 

',
        ];

        yield 'an warning occurred' => [
            'rules' => [],
            'errors' => [],
            'warnings' => [Warning::tokenIsInMoreThanOneLayer(ClassLikeToken::fromFQCN('Foo\Bar')->toString(), ['Layer 1', 'Layer 2'])],
            ' ------------------------------------------------------------------------------------------------------------------------- 
  Warnings                                                                                                                 
 ------------------------------------------------------------------------------------------------------------------------- 
  Foo\Bar is in more than one layer ["Layer 1", "Layer 2"]. It is recommended that one token should only be in one layer.  
 ------------------------------------------------------------------------------------------------------------------------- 


 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            0    
  Allowed              0    
  Warnings             1    
  Errors               0    
 -------------------- ----- 

',
        ];
    }

    /**
     * @dataProvider basicDataProvider
     */
    public function testBasic(array $rules, array $errors, array $warnings, string $expectedOutput, bool $reportUncovered = true, bool $reportSkipped = true): void
    {
        $bufferedOutput = new BufferedOutput();
        $output = new SymfonyOutput(
            $bufferedOutput,
            new Style(new SymfonyStyle($this->createMock(InputInterface::class), $bufferedOutput))
        );

        $formatter = new TableOutputFormatter();
        $formatter->finish(
            new LegacyResult($rules, $errors, $warnings),
            $output,
            new OutputFormatterInput(
                null,
                $reportSkipped,
                $reportUncovered,
                false
            )
        );

        static::assertSame($expectedOutput, $bufferedOutput->fetch());
    }
}
