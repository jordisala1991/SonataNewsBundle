<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Sonata\NewsBundle\Controller\Api;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Request\ParamFetcherInterface;

use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Sonata\AdminBundle\Datagrid\Pager;
use Sonata\NewsBundle\Model\Comment;
use Sonata\NewsBundle\Model\Post;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Class PostController
 *
 * @package Sonata\NewsBundle\Controller\Api
 *
 * @author Hugo Briand <briand@ekino.com>
 */
class PostController extends FOSRestController
{
    /**
     * Retrieves the list of posts (paginated)
     *
     * @ApiDoc(
     *  resource=true,
     *  output={"class"="Sonata\NewsBundle\Model\Post", "groups"="sonata_api_read"}
     * )
     *
     * @QueryParam(name="page", requirements="\d+", default="1", description="Page for posts list pagination")
     * @QueryParam(name="count", requirements="\d+", default="10", description="Number of posts by page")
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return Post[]
     */
    public function getPostsAction(ParamFetcherInterface $paramFetcher)
    {
        $page  = $paramFetcher->get('page');
        $count = $paramFetcher->get('count');

        /** @var Pager $postsPager */
        $postsPager = $this->get('sonata.news.manager.post')->getPager(array(), $page, $count);

        return $postsPager->getResults();
    }

    /**
     * Retrieves a specific post
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="post id"}
     *  },
     *  output={"class"="Sonata\NewsBundle\Model\Post", "groups"="sonata_api_read"},
     *  statusCodes={
     *      200="Returned when successful",
     *      404="Returned when post is not found"
     *  }
     * )
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param $id
     *
     * @return Post
     */
    public function getPostAction($id)
    {
        return $this->getPost($id);
    }

    /**
     * Retrieves the comments of specified post
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="post id"}
     *  },
     *  output={"class"="Sonata\NewsBundle\Model\Comment", "groups"="sonata_api_read"},
     *  statusCodes={
     *      200="Returned when successful",
     *      404="Returned when post is not found"
     *  }
     * )
     *
     * @View(serializerGroups="sonata_api_read", serializerEnableMaxDepthChecks=true)
     *
     * @param $id
     *
     * @return Comment[]
     */
    public function getPostCommentsAction($id)
    {
        return $this->getPost($id)->getComments();
    }

    /**
     * Adds a comment to a post
     *
     * @ApiDoc(
     *  requirements={
     *      {"name"="id", "dataType"="integer", "requirement"="\d+", "description"="post id"}
     *  },
     *  input={
     *      "class"="Sonata\NewsBundle\Form\Type\CommentType",
     *      "name"="comment",
     *  },
     *  output="Sonata\NewsBundle\Model\Comment",
     *  statusCodes={
     *      200="Returned when successful",
     *      403="Returned when invalid parameters",
     *      404="Returned when post is not found"
     *  }
     * )
     *
     * @param int $id Post id
     *
     * @return Comment|FormInterface
     * @throws HttpException
     */
    public function postPostCommentsAction($id)
    {
        $post = $this->getPost($id);

        if (!$post->isCommentable()) {
            throw new HttpException(403, sprintf('Post (%d) not commentable', $id));
        }

        $comment = $this->get('sonata.news.manager.comment')->create();
        $comment->setPost($post);
        $comment->setStatus($post->getCommentsDefaultStatus());

        $form = $this->get('form.factory')->createNamed('comment', 'sonata_post_comment', $comment, array('csrf_protection' => false));
        $form->bind($this->getRequest());

        if ($form->isValid()) {
            $comment = $form->getData();

            $this->get('sonata.news.manager.comment')->save($comment);
            $this->get('sonata.news.mailer')->sendCommentNotification($comment);

            $view = \FOS\RestBundle\View\View::create($comment);
            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups(array('sonata_api_read'));
            $serializationContext->enableMaxDepthChecks();
            $view->setSerializationContext($serializationContext);

            return $view;
        }

        return $form;
    }

    /**
     * Retrieves post with id $id or throws an exception if it doesn't exist
     *
     * @param $id
     *
     * @return Post
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function getPost($id)
    {
        $post = $this->get('sonata.news.manager.post')->findOneBy(array('id' => $id));

        if (null === $post) {
            throw new NotFoundHttpException(sprintf('Post (%d) not found', $id));
        }

        return $post;
    }
}