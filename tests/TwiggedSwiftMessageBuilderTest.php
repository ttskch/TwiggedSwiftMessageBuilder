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

    public function test_finishEmbedImage()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getBody()->thenReturn('placeholder');
        Phake::when($message)->embed(Phake::anyParameters())->thenReturn('replacement');

        $builder = $this->getBuilder();
        $builder->finishEmbedImage($message);

        Phake::verify($message)->setBody('replacement');
    }

    public function test_renderBody()
    {
        $message = Phake::mock('Swift_Message');
        Phake::when($message)->getBody()->thenReturn('placeholder');

        $placeholder = new Placeholder('placeholder', __DIR__ . '/templates/images/silex.png');

        $builder = $this->getBuilder($placeholder);
        $body = $builder->renderBody($message);

        $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($placeholder->getImagePath()));
        $this->assertEquals($base64, $body);
    }

    private function getBuilder(Placeholder $placeholder = null)
    {
        $twig = $this->getMockTwigEnvironment();
        $embedder = $this->getMockEmbedder($placeholder);
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

    private function getMockEmbedder(Placeholder $placeholder = null)
    {
        if (is_null($placeholder)) {
            $placeholder = new Placeholder('placeholder', '/path/to/image');
        }
        $placeholders = array($placeholder);

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
