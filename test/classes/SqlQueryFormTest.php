<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationParameters;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Encoding;
use PhpMyAdmin\Html\MySQLDocumentation;
use PhpMyAdmin\SqlQueryForm;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

use function __;
use function htmlspecialchars;

#[CoversClass(SqlQueryForm::class)]
class SqlQueryFormTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    private SqlQueryForm $sqlQueryForm;

    /**
     * Test for setUp
     */
    protected function setUp(): void
    {
        parent::setUp();

        parent::setLanguage();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dummyDbi = $this->createDbiDummy();
        $this->dummyDbi->addResult(
            'SHOW FULL COLUMNS FROM `PMA_db`.`PMA_table`',
            [['field1', 'Comment1']],
            ['Field', 'Comment'],
        );

        $this->dummyDbi->addResult(
            'SHOW INDEXES FROM `PMA_db`.`PMA_table`',
            [],
        );
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;
        $this->sqlQueryForm = new SqlQueryForm(new Template(), $this->dbi);

        //$GLOBALS
        $GLOBALS['db'] = 'PMA_db';
        $GLOBALS['table'] = 'PMA_table';
        $GLOBALS['text_dir'] = 'text_dir';
        $GLOBALS['server'] = 0;

        $config = Config::getInstance();
        $config->settings['GZipDump'] = false;
        $config->settings['BZipDump'] = false;
        $config->settings['ZipDump'] = false;
        $config->settings['ServerDefault'] = 'default';
        $config->settings['TextareaAutoSelect'] = true;
        $config->settings['TextareaRows'] = 100;
        $config->settings['TextareaCols'] = 11;
        $config->settings['DefaultTabDatabase'] = 'structure';
        $config->settings['RetainQueryBox'] = true;
        $config->settings['ActionLinksMode'] = 'both';
        $config->settings['DefaultTabTable'] = 'browse';
        $config->settings['CodemirrorEnable'] = true;
        $config->settings['DefaultForeignKeyChecks'] = 'default';

        $relationParameters = RelationParameters::fromArray([
            'table_coords' => 'table_name',
            'displaywork' => true,
            'db' => 'information_schema',
            'table_info' => 'table_info',
            'relwork' => true,
            'relation' => 'relation',
        ]);
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, $relationParameters);

        $config->selectedServer['user'] = 'user';
        $config->selectedServer['pmadb'] = 'pmadb';
        $config->selectedServer['bookmarktable'] = 'bookmarktable';
    }

    /**
     * Test for getHtmlForInsert
     */
    public function testPMAGetHtmlForSqlQueryFormInsert(): void
    {
        //Call the test function
        $query = 'select * from PMA';
        $html = $this->sqlQueryForm->getHtml('PMA_db', 'PMA_table', $query);

        //validate 1: query
        $this->assertStringContainsString(
            htmlspecialchars($query),
            $html,
        );

        //validate 2: enable auto select text in textarea
        $autoSel = ' data-textarea-auto-select="true"';
        $this->assertStringContainsString($autoSel, $html);

        //validate 3: MySQLDocumentation::show
        $this->assertStringContainsString(
            MySQLDocumentation::show('SELECT'),
            $html,
        );

        //validate 4: $fields_list
        $this->assertStringContainsString('<input type="button" value="DELETE" id="delete"', $html);
        $this->assertStringContainsString('<input type="button" value="UPDATE" id="update"', $html);
        $this->assertStringContainsString('<input type="button" value="INSERT" id="insert"', $html);
        $this->assertStringContainsString('<input type="button" value="SELECT" id="select"', $html);
        $this->assertStringContainsString('<input type="button" value="SELECT *" id="selectall"', $html);

        //validate 5: Clear button
        $this->assertStringContainsString('<input type="button" value="DELETE" id="delete"', $html);
        $this->assertStringContainsString(
            __('Clear'),
            $html,
        );
    }

    /**
     * Test for getHtml
     */
    public function testPMAGetHtmlForSqlQueryForm(): void
    {
        //Call the test function
        $GLOBALS['lang'] = 'ja';
        $query = 'select * from PMA';
        $html = $this->sqlQueryForm->getHtml('PMA_db', 'PMA_table', $query);

        //validate 1: query
        $this->assertStringContainsString(
            htmlspecialchars($query),
            $html,
        );

        //validate 2: $enctype
        $enctype = ' enctype="multipart/form-data">';
        $this->assertStringContainsString($enctype, $html);

        //validate 3: sqlqueryform
        $this->assertStringContainsString('id="sqlqueryform" name="sqlform"', $html);

        //validate 4: $db, $table
        $table = $GLOBALS['table'];
        $db = $GLOBALS['db'];
        $this->assertStringContainsString(
            Url::getHiddenInputs($db, $table),
            $html,
        );

        //validate 5: $goto
        $goto = empty($GLOBALS['goto']) ? Url::getFromRoute('/table/sql') : $GLOBALS['goto'];
        $this->assertStringContainsString(
            htmlspecialchars($goto),
            $html,
        );

        //validate 6: Kanji encoding form
        $this->assertStringContainsString(
            Encoding::kanjiEncodingForm(),
            $html,
        );
        $GLOBALS['lang'] = 'en';
    }
}
