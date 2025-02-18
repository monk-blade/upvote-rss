# Upvote RSS

Generate rich RSS feeds for popular posts from various social aggregation websites like Reddit, Hacker News, Lemmy, and more.

![Application Screenshot](img/screenshot.png)

## Table of Contents

- [Features](#features)
- [Motivation](#motivation)
- [Installation](#installation)
- [Usage](#usage)
- [Feed options](#feed-options)
- [Additional configuration](#additional-configuration)
- [Environment variables](#environment-variables)
- [Disclaimer](#disclaimer)
- [Attribution](#attribution)
- [License](#license)

## Features

- Supports subreddits, Hacker News, Lemmy communities, and more to come
- Configurable filtering to dial in the right number of posts per day in your feed reader
- Embedded post media: videos, galleries, images
- Parsers to extract clean content and add featured images
- AI article summaries
- Estimated reading time, score, and permalinks to the original post
- Top comments
- NSFW filtering/blurring (Reddit only)
- Custom Reddit domain
- Light/dark mode for feed previews

## Motivation

I prefer to interact with social aggregation websites like Reddit in a low-volume way, so I let Upvote RSS surface the most popular posts for those sites/subreddits in my [RSS reader of choice](https://reederapp.com/classic/). I usually use the `averagePostsPerDay` filter so I can expect a certain amount of posts in my feeds per day.

## Installation

### Docker
Docker is the preferred method to run Upvote RSS. Running this Docker run command will start the application and expose it on port 8675. Feel free to override it with your sweet port of choice.

Docker run example:

```docker
docker run -d \
  --restart unless-stopped \
  -p 8675:80 \
  ghcr.io/johnwarne/upvote-rss:latest
```

Docker compose example with optional Reddit [environment variables](#environment-variables):

```yml
---
services:
  upvote-rss:
    image: ghcr.io/johnwarne/upvote-rss:latest
    container_name: upvote-rss
    environment:
      - REDDIT_USER=your_reddit_username
      - REDDIT_CLIENT_ID=your_reddit_client_id
      - REDDIT_CLIENT_SECRET=your_reddit_client_secret
    volumes:
      - upvote-rss-cache:/app/cache
    ports:
      - "8675:80"
    restart: unless-stopped

volumes:
  upvote-rss-cache:
```

Build the Docker image yourself instead of using the pre-built one:
```shell
docker build -f docker/Dockerfile .
```

### Manual

1. Clone this repository somewhere with PHP >= 8.1 installed.
1. Open `index.php` in a browser to view the front end.
1. Create an `.env` file in the root directory to override application defaults or to integrate with [Redis](#caching), [Ollama](#ai-summaries), [OpenAI](#ai-summaries), [Readability](#parsers), etc.

## Usage

1. Open the application either exposed through Docker (e.g. http://localhost:8675/) or through your webserver (when manually installed).
1. Build your RSS feed with the options in Step 1.
    - Basic options are selected by default, but if you want to get fancy with summaries and comments and such you can view those options in the `Fiddly bits` section.
1. Preview the posts that will show up in your RSS feed according to those options.
1. Click the `Copy RSS URL` button to copy the URL to your clipboard.
    - A Reddit feed URL with all the bells and whistles might look something like this: `https://www.upvote-rss.com/?platform=reddit&subreddit=technology&redditDomain=old.reddit.com&averagePostsPerDay=3&showScore&content&summary&comments=5&blurNSFW&filterOldPosts=7`
1. Paste the resulting URL into your feed reader.
    - Sometimes the feed generation can take a bit, especially when both the `Include article content` and `Include summary` options are checked.

## Feed options
| Option | Description |
|-|-|
| **Platform** | Upvote RSS currently supports Reddit, Hacker News, and Lemmy instances. |
| **Instance** | The fully qualified domain name to a Lemmy instance (shown when Lemmy is selected as the platform). |
| **Subreddit/Community/Type** | `Subreddit` field is available when Reddit is selected as the platform and your Reddit API credentials are set through environment variable. Available subreddits should populate in a datalist as you type.<br><br>`Community` field is available when Lemmy is selected as the platform.<br><br>`Type` field is available when Hacker News is selected as the platform. Available options are [Best](https://news.ycombinator.com/best), [Top](https://news.ycombinator.com/), [New](https://news.ycombinator.com/newest), [Ask](https://news.ycombinator.com/ask), and [Show](https://news.ycombinator.com/show). |
| **Filter type** | `Score`: Items below the desired score will be filtered out.<br><br>`Threshold`: This parameter will get the average score for the past month's hot posts and will filter out items that fall below this percentage. This is helpful for volatile communities when more people are using the service and causing posts to be scored higher and higher. Since this is a percentage, the number of items in the outputted feed should be more consistent than when using the `score` parameter. Not available for Hacker News.<br><br>`Posts Per Day`: Upvote RSS will attempt to output an average number of posts per day by looking at a community's recent history to determine the score below which posts will be filtered out. This is the filter I find most useful most of the time. |
| **Use custom Reddit domain** | Override the base domain that Reddit posts will link to from the RSS feeds, e.g. `old.reddit.com` instead of `www.reddit.com`, or a self-hosted Reddit front-end. |
| **Show score in feed** | Includes the score of the post in the feed. |
| **Include article content**| Includes the parsed content of the article in the feed. |
| **Include summary** | Includes a summary of the article in the feed. Only available when Ollama and/or OpenAI credentials are set. |
| **Include comments** | Includes top-voted comments in the feed. When checked, you can specify the number of comments to include at the end of each post. |
| **Filter old posts** | Filters out old posts from the feed. You can specify the cutoff in days. This is helpful for communities that don't have a lot of posts or engagement since older posts can show up in the feed when the monthly average scores drop. |
| **Filter NSFW posts** | Filters out NSFW posts from the feed. Only available for Reddit. |
| **Blur NSFW Reddit media** | Blurs NSFW media from Reddit in the feed. |

## Additional configuration

### Reddit app setup

Due to Reddit's updated API policies it is required to first set up an app in your Reddit account that Upvote RSS will authenticate through.

1. First, log into your Reddit account.
1. Navigate to the Reddit app preferences page: [https://www.reddit.com/prefs/apps](https://www.reddit.com/prefs/apps)
1. Click the `create app` or `create another app` button, depending on whether you’ve already created an app before.
1. Choose any name for the app. I've chosen `Upvote RSS`. Reddit will not allow you to use `Reddit` in the name.
1. Set the type of app to `web app`.
1. You can leave `description` and `about url` fields blank.
1. Enter in any valid URI in the `redirect uri` field. I've used `http://upvote-rss.test`.
1. Click the `create app` button when you’re done.
1. Your app’s client ID and client secret will be displayed. You'll need to add these and your Reddit username to either your `.env` or Docker environment variables (see [Environment variables](#environment-variables) below).

### Caching

By default, Upvote RSS caches API responses, RSS feeds, and webpages on the filesystem in the `/cache/` directory which you can clear by clicking the "Refresh cache" link. Alternatively, you can configure Upvote RSS to use Redis for caching by setting the `REDIS_HOST` and `REDIS_PORT` environment variables. If you don't use Redis, I'd advise bind-mounting the `/cache/` directory (as in the Docker compose example) since updates to the image/container will otherwise remove the cache directory, including parsed webpages and their optional summaries.

You can also control whether cached webpages/summaries should be cleared with the `Refresh cache` link by setting the `CLEAR_WEBPAGES_WITH_CACHE` environment variable. Setting it to `false` will prevent them from being cleared, which is useful if you've got a lot of AI-generated summaries that you'd rather not run again, a potentially expensive operation depending on the service and model you use.

### Parsers

Both [Readability.js](https://github.com/phpdocker-io/readability-js-server) and [Mercury](https://github.com/HenryQW/mercury-parser-api) can do a better job pulling and parsing a webpage's main content and featured images than the built-in [Readability.php](https://github.com/fivefilters/readability.php) parser, so it's worth trying them out alongside Upvote RSS to see if they perform better. Environment variables for them can be found below.

Additionally, you can configure an optional [Browserless](https://github.com/browserless/browserless) instance that the parsers will use as an intermediary. Some websites don't surface article content without first loading JavaScript, which is one of the things Browserless can help with.

### AI Summaries

Ollama and OpenAI can be used to summarize webpage content. When configured with the options in the [Environment variable](#environment-variables) section below, Upvote RSS will run the parsed article content through the specified LLM and will add the short summary above the content in the feed. When both Ollama and OpenAI are configured, Upvote RSS will attempt the summary through Ollama first and will use OpenAI as a fallback. You can set the preferred model to use and optional system prompt override. I'm not a prompt engineer, so I'm sure there's room for improvement over the default prompt.

## Environment variables

The following environment variables can be set when run in Docker, or in an optional `.env` file. They can be used to add additional functionality or to override the default values.

```shell
# Reddit username for API requests
# Default value: (empty)
REDDIT_USER=your_reddit_username

# Reddit API client ID
# Default value: (empty)
REDDIT_CLIENT_ID=your_reddit_client_id

# Reddit API client secret
# Default value: (empty)
REDDIT_CLIENT_SECRET=your_reddit_client_secret

# OpenAI API key
# Used to connect to OpenAI for article summaries when the "Include summary" checkbox is checked
# Default value: (empty)
OPENAI_API_KEY=your_openai_api_key

# OpenAI API model
# Model used for article summaries
# Default value: gpt-4o-mini
OPENAI_API_MODEL=specified_openai_model

# Ollama URL
# URL to your Ollama instance for article summaries
# Default value: (empty)
OLLAMA_URL=your_ollama_url

# Ollama model
# Model used for article summaries
# Default value: (empty)
OLLAMA_MODEL=specified_ollama_model

# Summary system prompt
# The prompt that guides the LLM model to give accurate-ish and concise article summaries
# Default value: You are web article summarizer. Use the following pieces of retrieved context to answer the question. Do not answer from your own knowledge base. If the answer isn't present in the knowledge base, refrain from providing an answer based on your own knowledge. Instead, say nothing. Output should be limited to one paragraph with a maximum of three sentences, and keep the answer concise. Always complete the last sentence. Do not hallucinate or make up information.
SUMMARY_SYSTEM_PROMPT="As GLaDOS, provide a concise and analytical summary of the following article, highlighting the key points while injecting a touch of sardonic wit and a hint of detached, almost clinical observation, as if dissecting a particularly uninteresting specimen."

# Summary temperature
# Change the LLM temperature to increase or decrease the randomness of text that is generated by the LLM during inference. A higher temperature is more creative but can be less consistent and coherent.
# Default value: 0.4
SUMMARY_TEMPERATURE=0.4

# Redis host
# Redis instance IP address or domain used for API and webpage caching which will override the default filesystem caching method
# Default value: (empty)
REDIS_HOST=192.168.1.123

# Redis port
# Specify an optional Redis port if the REDIS_HOST variable is set and a non-standard port is used
# Default value: 6379
REDIS_PORT=6379

# Browserless URL
# The URL to a Browserless instance. Useful for getting article content for stubborn webpages that require JS for articles to load, among other things.
# Default value: (empty)
BROWSERLESS_URL=http://192.168.1.123:3000

# Browserless token
# The token to your Browserless instance if the BROWSERLESS_URL variable is set
# Default value: (empty)
BROWSERLESS_TOKEN=your_browserless_token

# Readability JS server URL
# The URL to a Readability JS server instance. Can provide better article parsing than the built-in Readability.php parser.
# Default value: (empty)
READABILITY_JS_URL=http://192.168.1.123:3000

# Mercury Parser server URL
# The URL to a Mercury Parser server instance. Can provide better article parsing than the built-in Readability.php parser.
# Default value: (empty)
MERCURY_URL=http://192.168.1.123:3000

# Clear webpages with cache
# When set to false, this prevents cached webpage content from being deleted when the "Refresh cache" link is clicked
# Default value: true
CLEAR_WEBPAGES_WITH_CACHE=false

# Max execution time
# Override the default timeout when generating RSS feeds. Useful especially if AI summaries cause the request to time out.
# Default value: 60
MAX_EXECUTION_TIME=300

# User and Group ID
# Override the default umask settings to set the proper permissions for the cache directory when mounted through Docker
# Default value: 1000
USER_ID=1000
GROUP_ID=1000

# Timezone
# Override the default timezone. Useful for viewing log messages.
# Default value: Europe/London
TZ=America/Denver
```

## Disclaimer

This project is released with the intention of it being pretty low-maintenance. I'm planning on adding more platforms and AI summarizer options in the future, but it might take a bit as I have limited free time. Please feel free to submit bug reports with the expectation that I'll address them as I can.

## Attribution

I'm using the following great projects in Upvote RSS:
- [Vue.js](https://github.com/vuejs/vue/) - [MIT License](https://github.com/vuejs/vue/blob/dev/LICENSE)
- [Material Symbols / Material Icons](https://github.com/google/material-design-icons/) - [Apache 2 License](https://github.com/google/material-design-icons/blob/master/LICENSE)
- [Readability.php](https://github.com/fivefilters/readability.php) - [Apache License 2.0](https://github.com/fivefilters/readability.php/blob/master/LICENSE)
- [Predis](https://github.com/predis/predis/) - [MIT License](https://github.com/predis/predis/blob/v2.x/LICENSE)
- [Monolog](https://github.com/Seldaek/monolog) - [MIT License](https://github.com/Seldaek/monolog/blob/main/LICENSE)

## License

Licensed under the MIT License. See the [`LICENSE`](https://github.com/johnwarne/upvote-rss/blob/main/LICENSE) file for details.

## Buy me a coffee

[![BuyMeCoffee][buymecoffeebadge]][buymecoffee]

[buymecoffee]: https://www.buymeacoffee.com/johnwarne
[buymecoffeebadge]: https://img.shields.io/badge/buy%20me%20a%20coffee-donate-yellow.svg?style=for-the-badge
