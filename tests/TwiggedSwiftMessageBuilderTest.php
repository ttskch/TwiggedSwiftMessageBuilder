<?php
namespace Qck\TwiggedSwiftMessageBuilder;

use Phake;
use Qck\TwiggedSwiftMessageBuilder\ImageEmbedder\Placeholder;

class TwiggedSwiftMessageBuilderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
    }

    public function test_buildMessage()
    {
        $builder = $this->getBuilder();

        /** @var \Swift_Message $message */
        $message = $builder->buildMessage('/path/to/template');

        $this->assertEquals(array('from@test.com' => 'from_name'), $message->getFrom());
        $this->assertEquals(array('to@test.com' => null), $message->getTo());
        $this->assertEquals(array('cc@test.com' => null), $message->getCc());
        $this->assertEquals(array('bcc@test.com' => null), $message->getBcc());
        $this->assertEquals(array('reply_to@test.com' => null), $message->getReplyTo());
        $this->assertEquals('subject', $message->getSubject());
        $this->assertEquals('body', $message->getBody());
    }

    public function test_setInlineStyle()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getContentType()->thenReturn('text/html');

        $builder = $this->getBuilder();
        $builder->setInlineStyle($message, 'style');

        Phake::verify($message)->setBody('styled html');
    }

    public function test_setInlineStyle_error_for_plain_text()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getContentType()->thenReturn('text/plain');

        $this->setExpectedException('Qck\TwiggedSwiftMessageBuilder\Exception\RuntimeException');

        $builder = $this->getBuilder();
        $builder->setInlineStyle($message, 'style');
    }

    public function test_finalizeEmbedding()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getBody()->thenReturn('placeholder');
        Phake::when($message)->embed(Phake::anyParameters())->thenReturn('replacement');

        $builder = $this->getBuilder();
        $builder->finalizeEmbedding($message);

        Phake::verify($message)->setBody('replacement');
    }

    public function test_renderBody()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getBody()->thenReturn('placeholder');

        $placeholders = array(new Placeholder('placeholder', __DIR__ . '/templates/images/silex.png'));

        $builder = $this->getBuilder($placeholders);
        $body = $builder->renderBody($message);

        $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($placeholders[0]->getImagePath()));
        $this->assertEquals($base64, $body);
    }

    public function test_renderBody_with_no_images()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getBody()->thenReturn('normal body');

        $builder = $this->getBuilder(array());
        $body = $builder->renderBody($message);

        $this->assertEquals('normal body', $body);
    }

    private function getBuilder(array $placeholders = null)
    {
        $twig = $this->getMockTwigEnvironment();
        $embedder = $this->getMockEmbedder($placeholders);
        $styler = $this->getMockStyler();

        return new TwiggedSwiftMessageBuilder($twig, $embedder, $styler);
    }

    private function getMockTwigEnvironment()
    {
        $params = array();

        $template = Phake::mock('Twig_Template');
        Phake::when($template)->renderBlock('from', $params)->thenReturn('from@test.com');
        Phake::when($template)->renderBlock('from_name', $params)->thenReturn('from_name');
        Phake::when($template)->renderBlock('to', $params)->thenReturn('to@test.com');
        Phake::when($template)->renderBlock('cc', $params)->thenReturn('cc@test.com');
        Phake::when($template)->renderBlock('bcc', $params)->thenReturn('bcc@test.com');
        Phake::when($template)->renderBlock('reply_to', $params)->thenReturn('reply_to@test.com');
        Phake::when($template)->renderBlock('subject', $params)->thenReturn('subject');
        Phake::when($template)->renderBlock('body', $params)->thenReturn('body');

        $twig = Phake::mock('Twig_Environment');
        Phake::when($twig)->loadTemplate(Phake::anyParameters())->thenReturn($template);

        return $twig;
    }

    private function getMockEmbedder(array $placeholders = null)
    {
        if (is_null($placeholders)) {
            $placeholders = array(new Placeholder('placeholder', '/path/to/image'));
        }

        $embedder = Phake::mock('Qck\TwiggedSwiftMessageBuilder\ImageEmbedder\Embedder');
        Phake::when($embedder)->extractPlaceholders(Phake::anyParameters())->thenReturn($placeholders);
        Phake::when($embedder)->extractPlaceholders(Phake::anyParameters())->thenReturn($placeholders);

        return $embedder;
    }

    private function getMockStyler()
    {
        $styler = Phake::mock('TijsVerkoyen\CssToInlineStyles\CssToInlineStyles');
        Phake::when($styler)->convert()->thenReturn('styled html');

        return $styler;
    }
}
