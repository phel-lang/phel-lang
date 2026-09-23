<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use DateTime;
use DateTimeImmutable;
use Phel\Compiler\Application\GlobalEnvironmentManager;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;

/**
 * A tag spelled with a `:use` import names the class the DECLARING namespace
 * imported. Reading it later from another namespace, from a spliced body or
 * after the environment was swapped must not re-resolve the short name
 * against whatever namespace is active at that point.
 */
final class TagImportOwnerNamespaceTest extends AbstractCompilerRuntimeTestCase
{
    public function test_a_caller_with_a_clashing_import_infers_the_callee_class(): void
    {
        $this->compilerFacade->eval(
            '(ns tag-owner.a (:use DateTimeImmutable :as Moment))
             (defn ^Moment make [] (DateTimeImmutable. "2026-01-02"))',
        );
        $this->compilerFacade->eval('(ns tag-owner.b (:use DateTime :as Moment))');

        $php = $this->compilerFacade->compile('(defn build [] (tag-owner.a/make))')->getPhpCode();

        self::assertStringContainsString('\DateTimeImmutable', $php);
        self::assertStringNotContainsString('\DateTime ', $php);
        self::assertStringNotContainsString('\DateTime{', $php);

        $this->compilerFacade->eval('(defn build [] (tag-owner.a/make))');
        self::assertInstanceOf(DateTimeImmutable::class, $this->compilerFacade->eval('(build)'));
    }

    public function test_definition_meta_stores_the_rooted_class(): void
    {
        $this->compilerFacade->eval('(ns tag-owner.meta (:use DateTimeImmutable :as Moment))');
        $this->compilerFacade->eval('(defn ^Moment stamp [] (DateTimeImmutable.))');

        $meta = self::$globalEnv->getDefinition('tag-owner.meta', Symbol::create('stamp'));

        self::assertNotNull($meta);
        self::assertSame('\DateTimeImmutable', $meta->find(Keyword::create('tag')));
    }

    public function test_a_local_tag_is_resolved_where_it_is_declared(): void
    {
        $this->compilerFacade->eval('(ns tag-owner.local (:use DateTime :as Moment))');

        $php = $this->compilerFacade->compile(
            '(fn [^Moment d] (let [^Moment e d] (foreach [^Moment x [e]] x) e))',
        )->getPhpCode();

        self::assertStringContainsString('\DateTime $d', $php);
        self::assertStringNotContainsString('Moment', $php);
    }

    public function test_a_temporary_environment_does_not_leak_into_tag_resolution(): void
    {
        $this->compilerFacade->eval('(ns tag-owner.restore (:use DateTime :as Moment))');
        $current = GlobalEnvironmentRegistry::get();
        $registry = Registry::getInstance()->snapshot();

        // What PhelFunctionRuntimeLoader does: build a throwaway environment
        // (which used to install its own `:use` lookup), then put the
        // previous environment and registry back.
        $manager = new GlobalEnvironmentManager();
        $manager->initializeNew()->addUseAlias('tag-owner.restore', Symbol::create('Moment'), Symbol::create('\\Wrong'));
        Registry::getInstance()->restore($registry);
        $manager->setInstance(self::$globalEnv);
        self::assertSame($current, GlobalEnvironmentRegistry::get());
        self::$globalEnv->setNs('tag-owner.restore');

        $php = $this->compilerFacade->compile('(fn [^Moment d] d)')->getPhpCode();

        self::assertStringContainsString('\DateTime $d', $php);
        self::assertInstanceOf(DateTime::class, $this->compilerFacade->eval('((fn [^Moment d] d) (DateTime.))'));
    }

    public function test_re_evaluating_a_namespace_keeps_its_resolution(): void
    {
        $source = '(ns tag-owner.reeval (:use DateTimeImmutable :as Moment))
                   (defn ^Moment again [] (DateTimeImmutable.))';

        $this->compilerFacade->eval($source);
        $this->compilerFacade->eval('(ns tag-owner.other (:use DateTime :as Moment))');
        $this->compilerFacade->eval($source);

        $meta = self::$globalEnv->getDefinition('tag-owner.reeval', Symbol::create('again'));

        self::assertNotNull($meta);
        self::assertSame('\DateTimeImmutable', $meta->find(Keyword::create('tag')));
        self::assertInstanceOf(DateTimeImmutable::class, $this->compilerFacade->eval('(tag-owner.reeval/again)'));
    }

    public function test_union_and_intersection_members_resolve_their_imports(): void
    {
        $this->compilerFacade->eval(
            '(ns tag-owner.composite
               (:use DateTimeImmutable :as Moment)
               (:use ArrayAccess :as Access)
               (:use Countable :as Sized))',
        );
        $source = '(definterface Holder
                     (held [this ^{:tag (Moment null)} at])
                     (sized [this ^{:tag [Access Sized]} b]))
                   (defstruct Box [^{:tag (Moment null)} at ^{:tag [Access Sized]} items]
                     Holder
                     (held [this at] at)
                     (sized [this b] (php/count b)))';

        $php = $this->compilerFacade->compile($source)->getPhpCode();

        self::assertStringContainsString('public function held(\DateTimeImmutable|null $at);', $php);
        self::assertStringContainsString('public function sized(\ArrayAccess&\Countable $b);', $php);
        self::assertStringContainsString('protected \DateTimeImmutable|null $at;', $php);
        self::assertStringContainsString('protected \ArrayAccess&\Countable $items;', $php);

        $this->compilerFacade->eval($source);
        $this->compilerFacade->eval('(def box (Box nil (ArrayObject. (php/array 1 2))))');

        self::assertInstanceOf(
            DateTimeImmutable::class,
            $this->compilerFacade->eval('(.held box (DateTimeImmutable. "2020-01-01"))'),
        );
        self::assertSame(3, $this->compilerFacade->eval('(.sized box (ArrayObject. (php/array 1 2 3)))'));
    }
}
