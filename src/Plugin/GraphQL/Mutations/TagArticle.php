<?php

namespace Drupal\mymutations\Plugin\GraphQL\Mutations;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql_core\GraphQL\EntityCrudOutputWrapper;
use Drupal\graphql\Plugin\GraphQL\Mutations\MutationPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GraphQL\Type\Definition\ResolveInfo;

/**
 *
 * @GraphQLMutation(
 *   id = "tag_article",
 *   secure = true,
 *   name = "tagArticle",
 *   type = "EntityCrudOutput",
 *   arguments = {
 *     "articleId" = {
 *       "type" = "String",
 *       "nullable" = false
 *     },
 *     "termIds" = {
 *       "type" = "[String]",
 *       "nullable" = false
 *     }
 *   }
 * )
 */
class TagArticle extends MutationPluginBase implements ContainerFactoryPluginInterface  {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
        $configuration, $pluginId, $pluginDefinition, $container->get('entity_type.manager'), $container->get('renderer')
    );
  }

  /**
   * TagArticle constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
  }

  public function resolve($value, array $args, ResolveContext $context, ResolveInfo $info) {
    // There are cases where the Drupal entity API calls emit the cache metadata
    // in the current render context. In such cases
    // EarlyRenderingControllerWrapperSubscriber throws the leaked cache
    // metadata exception. To avoid this, wrap the execution in its own render
    // context.
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($value, $args, $context, $info) {


          $storage = \Drupal::entityTypeManager()->getStorage('node');

          $vid = 'tags';
          $valid_tids = [];
          $valid_terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
            foreach ($valid_terms as $valid_term) {
                $valid_tids[] = $valid_term->tid;
            }

          /** @var \Drupal\node\Entity\Node $article */
          if (!$article = $storage->load($args['articleId'])) {
            return new EntityCrudOutputWrapper(NULL, NULL, [
              $this->t('The requested article could not be loaded : @nid.', ['@nid' => $args['articleId']]),
            ]);
          } else {

            if (!$article->bundle() === 'article') {
              return new EntityCrudOutputWrapper(NULL, NULL, [
                $this->t('The requested Node is not of the expected type article. @bundle found instead', ['@bundle' => $article->bundle()]),
              ]);
            } else {

              $tids = [];
              foreach ($article->field_tags->getValue() as $val) {
                $tids[] = $val['target_id'];
              }

              foreach ($args['termIds'] as $tid) {
                //we only ADD tags, those already in the article are kept
                if (!in_array($tid, $tids)) {

                    if (!$tid == $article->field_tags->appendItem($tid)) {
                        return new EntityCrudOutputWrapper(NULL, NULL, [
                          $this->t('Unable to add term to article', []),
                        ]);
                    } else {

                        $updated = true;
                    }
                }
              }
            }
          }

          if ($updated) {
            $article->save();
          }

          return new EntityCrudOutputWrapper($article);
        });
  }
}