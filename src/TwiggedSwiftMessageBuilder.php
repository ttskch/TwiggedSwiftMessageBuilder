<?php
namespace Ttskch\TwiggedSwiftMessageBuilder;

use Ttskch\TwiggedSwiftMessageBuilder\Exception\RuntimeException;
use Ttskch\TwiggedSwiftMessageBuilder\Twig\Extension\TwiggedSwiftMessageBuilderExtension;
use Ttskch\TwiggedSwiftMessageBuilder\ImageEmbedder\Embedder;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class TwiggedSwiftMessageBuilder
{
    /**
     * @var \Twig_Environment
     */
    private $twig;
    /**
     * @var ImageEmbedder\Embedder
     */
    private $embedder;
    /**
     * @var \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles
     */
    private $styler;

    /**
     * @param \Twig_Environment $twig
     * @param Embedder $embedder
     * @param CssToInlineStyles $styler
     */
    public function __construct(\Twig_Environment $twig, Embedder $embedder = null, CssToInlineStyles $styler = null)
    {
        if (is_null($embedder)) {
            $embedder = new Embedder();
        }
        if (is_null($styler)) {
            $styler = new CssToInlineStyles();
        }

        $this->twig = $twig;
        $this->embedder = $embedder;
        $this->styler = $styler;

        $this->twig->addExtension(new TwiggedSwiftMessageBuilderExtension($embedder));
    }

    /**
     * @param $templatePath
     * @param array $vars
     * @return \Swift_Message
     */
    public function buildMessage($templatePath, array $vars = array())
    {
        /** @var $template \Twig_Template */
        $template = $this->twig->loadTemplate($templatePath);
        $contentType = preg_match('/\.html(\.twig)?$/', $templatePath) ? 'text/html' : 'text/plain';

        $message = \Swift_Message::newInstance();

        // build message from twig template.
        if ($from = $template->renderBlock('from', $vars)) {
            if ($fromName = $template->renderBlock('from_name', $vars)) {
                $message->setFrom($from, $fromName);
            } else {
                $message->setFrom($from);
            }
        }
        if ($to = $template->renderBlock('to', $vars)) {
            $message->setTo($to);
        }
        if ($cc = $template->renderBlock('cc', $vars)) {
            $message->setCc($cc);
        }
        if ($bcc = $template->renderBlock('bcc', $vars)) {
            $message->setBcc($bcc);
        }
        if ($replyTo = $template->renderBlock('reply_to', $vars)) {
            $message->setReplyTo($replyTo);
        }
        if ($subject = $template->renderBlock('subject', $vars)) {
            $message->setSubject($subject);
        }
        if ($body = $template->renderBlock('body', $vars)) {
            $message->setBody($body, $contentType);
        }

        return $message;
    }

    /**
     * @param \Swift_Message $message
     * @param $style
     * @return \Swift_Message
     * @throws Exception\RuntimeException
     */
    public function setInlineStyle(\Swift_Message $message, $style)
    {
        if ($message->getContentType() !== 'text/html') {
            throw new RuntimeException('Plain text message cannot be styled.');
        }

        $html = $message->getBody();
        $html = mb_convert_encoding($html, 'html-entities', 'auto');

        $this->styler->setHTML($html);
        $this->styler->setCSS($style);
        $styledHtml = $this->styler->convert();
        $message->setBody($styledHtml);

        return $message;
    }

    /**
     * @param \Swift_Message $message
     * @return \Swift_Message
     */
    public function finalizeEmbedding(\Swift_Message $message)
    {
        $body = $message->getBody();

        /** @var \Ttskch\TwiggedSwiftMessageBuilder\ImageEmbedder\Placeholder[] $placeholders */
        $placeholders = $this->embedder->extractPlaceholders($body);

        foreach ($placeholders as $placeholder) {
            $replacement = $message->embed(\Swift_Image::fromPath($placeholder->getImagePath()));
            $body = str_replace($placeholder->getPlaceholder(), $replacement, $body);
        }

        $message->setBody($body);

        return $message;
    }

    /**
     * @param \Swift_Message $message
     * @return mixed|string
     */
    public function renderBody(\Swift_Message $message)
    {
        $body = $message->getBody();

        /** @var \Ttskch\TwiggedSwiftMessageBuilder\ImageEmbedder\Placeholder[] $placeholders */
        $placeholders = $this->embedder->extractPlaceholders($body);

        foreach ($placeholders as $placeholder) {

            $splFile = new \SplFileInfo($placeholder->getImagePath());
            $ext = $splFile->getExtension();

            $replacement = "data:image/{$ext};base64," . base64_encode(file_get_contents($placeholder->getImagePath()));
            $body = str_replace($placeholder->getPlaceholder(), $replacement, $body);
        }

        return $body;
    }

    /**
     * @param \Ttskch\TwiggedSwiftMessageBuilder\ImageEmbedder\Embedder $embedder
     */
    public function setEmbedder($embedder)
    {
        $this->embedder = $embedder;

        return $this;
    }
}
