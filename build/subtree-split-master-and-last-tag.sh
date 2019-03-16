#!/usr/bin/env bash
git subsplit init git@github.com:portiny/portiny.git

LAST_TAG=$(git tag -l  --sort=committerdate | tail -n1);

git subsplit publish --heads="master" --tags=$LAST_TAG packages/console:git@github.com:portiny/console.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/doctrine:git@github.com:portiny/doctrine.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/elasticsearch:git@github.com:portiny/elasticsearch.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/elasticsearch-nette:git@github.com:portiny/elasticsearch-nette.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/graphql:git@github.com:portiny/graphql.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/graphql-nette:git@github.com:portiny/graphql-nette.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/graphql-symfony:git@github.com:portiny/graphql-symfony.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/rabbitmq:git@github.com:portiny/rabbitmq.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/rabbitmq-nette:git@github.com:portiny/rabbitmq-nette.git
git subsplit publish --heads="master" --tags=$LAST_TAG packages/rabbitmq-symfony:git@github.com:portiny/rabbitmq-symfony.git

rm -rf .subsplit/
