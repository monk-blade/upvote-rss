<!DOCTYPE html>
<html lang="en">

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Upvote RSS</title>
	<meta name="description" content="Generate RSS feeds from social aggregation websites." />
	<style><?php include 'styles/style.css' ?></style>
	<link rel="icon" type="image/png" href="img/favicons/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="img/favicons/favicon.svg" />
	<link rel="shortcut icon" href="img/favicons/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="Upvote RSS" />
	<link rel="manifest" href="img/favicons/site.webmanifest" />
	<meta property="og:type" content="website" />
	<meta property="og:title" content="Upvote RSS" />
	<meta property="og:description" content="Generate rich RSS feeds from Reddit, Lemmy, Hacker News, Lobsters, and Mbin" />
	<meta property="og:locale" content="en_US" />
	<meta property="og:image" content="https://www.upvote-rss.com/img/screenshot.png" />
	<meta property="og:image:width" content="1400" />
	<meta property="og:image:height" content="1000" />
	<meta property="og:url" content="https://www.upvote-rss.com/" />
</head>

<body>

	<header>
		<a href=".">
			<img src="img/logo.svg" alt="Upvote RSS" class="logo" height="140" width="803">
		</a>
		<h1><span class="sr-only">Upvote RSS </span>Generate rich RSS feeds from Reddit, Lemmy, Hacker News, Lobsters, PieFed, and Mbin</h1>
		<?php if(DEMO_MODE) : ?>
			<p><a href="https://github.com/johnwarne/upvote-rss/" target="_blank">Self-host your own instance<svg class="icon icon-link" aria-hidden="true" focusable="false"><use xlink:href="#icon-link"></use></svg></a></p>
		<?php endif; ?>
	</header>

	<main id="app" v-cloak>

		<div class="columns">

			<!-- Step 1 -->
			<section id="step-1" class="step">
				<h2>Build your feed</h2>
				<form class="column-content" @submit.prevent="debouncedSearch">
					<div class="inner">
						<div class="form-group">
							<label for="platform">Platform</label>
							<select name="platform" id="platform" v-model="platform">
								<option value="hacker-news">Hacker News</option>
								<option value="lemmy">Lemmy</option>
								<option value="lobsters">Lobsters</option>
								<option value="piefed">PieFed</option>
								<option value="mbin">Mbin</option>
								<template v-if="demoMode">
									<option disabled>Reddit (available only self-hosted)</option>
								</template>
								<template v-else-if="!redditEnabled">
									<option disabled>Reddit (not configured)</option>
								</template>
								<template v-else>
									<option value="reddit">Reddit</option>
								</template>
							</select>
						</div>
						<div v-if="platform === 'reddit'" class="form-group">
							<label for="subreddit">Subreddit</label>
							<input list="subreddits" id="subreddit" name="subreddit" v-model="subreddit" placeholder="Subreddit" @input="debouncedSearch(600, $event)" />
							<datalist id="subreddits">
								<option v-for="subreddit in subreddits" :value="subreddit" />
								<option v-if="!subreddits.length" value="Loading..." />
							</datalist>
						</div>
						<div v-if="platform === 'hacker-news'" class="form-group">
							<label for="type">Type</label>
							<select name="type" id="type" v-model="community" @change="debouncedSearch(10, $event)">
								<option value="beststories">Best</option>
								<option value="topstories">Top</option>
								<option value="newstories">New</option>
								<option value="askstories">Ask</option>
								<option value="showstories">Show</option>
							</select>
						</div>
						<div v-if="platform === 'lobsters'" class="form-group">
							<label for="type">Type</label>
							<select name="type" id="type" v-model="communityType">
								<option value="all">All posts</option>
								<option value="category">Category</option>
								<option value="tag">Tag</option>
							</select>
						</div>
						<div v-if="platform === 'lobsters' && communityType === 'category'" class="form-group">
							<label for="category">Category</label>
							<input type="text" id="category" name="category" v-model="community" placeholder="compsci, culture, etc." @input="debouncedSearch(600, $event)" />
						</div>
						<div v-if="platform === 'lobsters' && communityType === 'tag'" class="form-group">
							<label for="tag">Tag</label>
							<input type="text" id="tag" name="tag" v-model="community" placeholder="web, ai, rust, etc." @input="debouncedSearch(600, $event)" />
						</div>
						<div v-if="platform === 'lemmy' || platform === 'mbin' || platform === 'piefed'" class="form-group">
							<label for="instance">Instance</label>
							<input type="text" id="instance" name="instance" v-model="instance" placeholder="Instance" @input="debouncedSearch(600, $event)" />
						</div>
						<div v-if="platform === 'lemmy' || platform === 'piefed'" class="form-group">
							<label for="community">Community</label>
							<input type="text" id="community" name="community" v-model="community" placeholder="Community" @input="debouncedSearch(600, $event)" />
						</div>
						<div v-if="platform === 'mbin'" class="form-group">
							<label for="community">Magazine</label>
							<input type="text" id="community" name="community" v-model="community" placeholder="Community" @input="debouncedSearch(600, $event)" />
						</div>
						<div class="row filter-type-row">
							<div class="form-group">
								<label for="filterType">Filter Type <svg class="icon icon-information-solid" @click="filterInformation">
										<use xlink:href="#icon-information-solid"></use>
									</svg>
								</label>
								<select name="filterType" id="filterType" v-model="filterType">
									<option v-if="scoreFilterAvailable" value="score">Score</option>
									<option v-if="thresholdFilterAvailable" value="threshold">Threshold</option>
									<option v-if="averagePostsPerDayFilterAvailable" value="averagePostsPerDay">Posts Per Day</option>
								</select>
							</div>
							<div v-if="filterType === 'score' && scoreFilterAvailable" class="form-group">
								<label for="score" class="sr-only">Score</label>
								<input type="number" id="score" name="score" placeholder="Score" min="0" pattern="[0-9]*" inputmode="numeric" v-model="score" @input="debouncedSearch(250, $event)" />
							</div>
							<div v-if="filterType === 'threshold'" class="form-group">
								<label for="threshold" class="sr-only">Percentage</label>
								<input type="number" id="threshold" name="threshold" placeholder="Threshold" min="1" pattern="[0-9]*" inputmode="numeric" v-model="threshold" @input="debouncedSearch(250, $event)" />
							</div>
							<div v-if="filterType === 'averagePostsPerDay' && averagePostsPerDayFilterAvailable" class="form-group">
								<label for="averagePostsPerDay" class="sr-only">Posts Per Day</label>
								<input type="number" id="averagePostsPerDay" name="averagePostsPerDay" placeholder="Posts per day" min="1" pattern="[0-9]*" inputmode="numeric" v-model="averagePostsPerDay" @input="debouncedSearch(250, $event)" />
							</div>
						</div>
						<div class="filter-information">
							<dl>
								<dt>Score</dt>
								<dd>Posts below the specified score <span v-if="filterType==='score'">({{ score }}) </span>will be filtered out.</dd>
								<template v-if="thresholdFilterAvailable">
									<dt>Threshold</dt>
									<dd>Posts below the specified percentage <span v-if="filterType==='threshold'">({{ threshold }}%) </span> of the community's monthly top posts' average score will be filtered out.</dd>
								</template>
								<dt>Posts Per Day</dt>
								<dd>The RSS feed will output roughly the specified number of posts per day<span v-if="filterType==='averagePostsPerDay'"> ({{ averagePostsPerDay }})</span>.</dd>
							</dl>
						</div>
						<fieldset class="fiddly-bits">
							<legend focusable="true" tabindex="0" @click="fiddlyBits" @keyup.enter="fiddlyBits">
								<span>Fiddly bits</span>
							</legend>
							<div class="inner">
								<div class="form-group checkbox" v-if="platform == 'reddit'">
									<input type="checkbox" id="override-reddit-domain" name="override-reddit-domain" v-model="overrideRedditDomain" />
									<label for="override-reddit-domain">Use custom Reddit domain</label>
								</div>
								<div class="form-group" v-if="platform == 'reddit' && overrideRedditDomain">
									<label for="reddit-domain" class="sr-only">Reddit domain</label>
									<input type="text" id="reddit-domain" name="reddit-domain" placeholder="Reddit domain" v-model="redditDomain" @input="debouncedSearch(600, $event)" />
								</div>
								<div class="form-group checkbox">
									<input type="checkbox" id="show-score" name="show-score" v-model="showScore" />
									<label for="show-score">Show score in feed</label>
								</div>
								<div class="form-group checkbox">
									<input type="checkbox" id="include-content" name="include-content" v-model="includeContent" />
									<label for="include-content">Include article content</label>
								</div>
								<div v-if="includeContent" class="form-group checkbox">
									<template v-if="summaryEnabled">
										<input type="checkbox" id="include-summary" name="include-summary" v-model="includeSummary" />
										<label for="include-summary">Include summary</label>
									</template>
									<template v-else>
										<input type="checkbox" id="include-summary" name="include-summary" disabled />
										<label for="include-summary">Include summary (not configured)</label>
									</template>
								</div>
								<div class="row comments-row">
									<div class="form-group checkbox">
										<input type="checkbox" id="include-comments" name="include-comments" v-model="includeComments" />
										<label for="include-comments">Include comments</label>
									</div>
									<div class="form-group" v-if="includeComments">
										<label for="comments" class="sr-only">Comments</label>
										<input type="number" id="comments" name="comments" placeholder="Comments" min="1" pattern="[0-9]*" v-model="comments" />
									</div>
								</div>
								<div class="form-group checkbox" v-if="includeComments && pinnedCommentsFilterAvailable">
									<input type="checkbox" id="filter-pinned-comments" name="filter-pinned-comments" v-model="filterPinnedComments" />
									<label for="filter-pinned-comments">Filter pinned comments</label>
								</div>
								<div class="row cutoff-row">
									<div class="form-group checkbox">
										<input type="checkbox" id="filter-old-posts" name="filter-old-posts" v-model="filterOldPosts" />
										<label for="filter-old-posts">
											<template v-if="filterOldPosts && postCutoffDays == 1">Include posts from the past day</template>
											<span v-else-if="filterOldPosts">Include posts from the past <span>{{ postCutoffDays }}</span> days</span>
											<template v-else>Filter old posts</template>
										</label>
									</div>
									<div class="form-group" v-if="filterOldPosts">
										<label for="post-cutoff-days" class="sr-only">Days to keep</label>
										<input type="number" id="post-cutoff-days" name="post-cutoff-days" placeholder="days to keep" min="1" pattern="[0-9]*" v-model="postCutoffDays" @input="debouncedSearch(250, $event)" />
									</div>
								</div>
								<div class="form-group checkbox" v-if="platform == 'reddit'">
									<input type="checkbox" id="filter-nsfw" name="filter-nsfw" v-model="filterNSFW" />
									<label for="filter-nsfw">Filter NSFW posts</label>
								</div>
								<div class="form-group checkbox" v-if="platform == 'reddit'">
									<input type="checkbox" id="blur-nsfw" name="blur-nsfw" v-model="blurNSFW" />
									<label for="blur-nsfw" v-if="!filterNSFW">Blur NSFW Reddit media</label>
								</div>
							</div>
						</fieldset>
						<div class="form-group generate">
							<button type="submit" class="button" @click.prevent="generateButton">Generate RSS Feed</button>
						</div>
					</div>
				</form>
			</section>


			<!-- Step 2 -->
			<section id="step-2" class="step" :class="{ loading: loading }">
				<h2>Preview your posts</h2>
				<div class="post-list column-content">
					<p v-if="error" class="error">{{ error }}</p>
					<p v-else-if="!loading && posts.length < 1" class="no-posts-found">No posts found that match the filters.</p>
					<article v-if="loading && posts.length < 1" v-for="index in 5" :key="index">
						<a href="#">
							<div class="thumbnail default-image" :class="{ skeleton: loading }">
								<img src="https://www.redditstatic.com/mweb2x/favicon/76x76.png" alt="Thumbnail">
							</div>
							<div class="content">
								<h3 :class="{ skeleton: loading }">Post title</h3>
								<time :class="{ skeleton: loading }">Time</time>
								<div class="score" :class="{ skeleton: loading }">
									<span>⬆︎</span>Score
								</div>
							</div>
						</a>
					</article>
					<article v-for="(post, index, key) in posts" :class="{ loading : loading }" :style="'--index: ' + index" :key="key">
						<a :href="post.permalink" target="_blank">
							<div v-if="blurNSFW && post.nsfw && post.thumbnail_obfuscated_url" class="thumbnail" :class="{ skeleton: loading }">
								<img :src="post.thumbnail_obfuscated_url" :alt="post.title">
							</div>
							<div v-else-if="(!blurNSFW || (blurNSFW && !post.nsfw) ) && post.thumbnail_url && post.thumbnail_url != 'self' && post.thumbnail_url != 'default'" class="thumbnail" :class="{ skeleton: loading }">
								<img :src="post.thumbnail_url" :alt="post.title">
							</div>
							<div v-else-if="community_icon" class="thumbnail default-image" :class="{ skeleton: loading }">
								<img :src="community_icon" :alt="community + ` logo`">
							</div>
							<div v-else-if="platform_icon" class="thumbnail default-image" :class="{ skeleton: loading }">
								<img :src="platform_icon" :alt="platform.charAt(0).toUpperCase() + platform.slice(1) + ` logo`">
							</div>
							<div class="content">
								<h3 v-html="post.title" :class="{ skeleton: loading }"></h3>
								<time v-if="post.relative_date" :datetime="post.time_rfc_822" v-html="post.relative_date" :class="{ skeleton: loading }"></time>
								<div v-if="post.score_formatted" class="score" :class="{ skeleton: loading }">
									<span>⬆︎</span>{{ post.score_formatted }}
								</div>
							</div>
						</a>
					</article>
					<progress-indicator>
						<progress max="100" :value="progress">{{ progress }}%</progress>
						<progress-percentage v-html="progress + `%`"></progress-percentage>
						<svg
							class="progress-ring"
							:height="progressRadius * 2"
							:width="progressRadius * 2"
						>
							<circle
								fill="transparent"
								:stroke-dasharray="progressCircumference + ' ' + progressCircumference"
								:style="`stroke-dashoffset: ` + progressStrokeDashOffset"
								:stroke-width="progressStroke"
								:r="progressNormalizedRadius"
								:cx="progressRadius"
								:cy="progressRadius"
							/>
						</svg>
					</progress-indicator>
				</div>
			</section>


			<!-- Step 3 -->
			<section id="step-3" class="step" :class="{ loading: loading }">
				<h2>Copy RSS URL</h2>
				<div class="column-content">
					<div class="inner">
						<a class="community" v-if="community_url || loading" :href="community_url" target="_blank" :title="community_title">
							<figure class="community-image" v-if="community_icon || loading" :class="{ skeleton: loading }">
								<img :src="community_icon" :alt="community_title" height="60" width="60">
								<figcaption class="nsfw" v-if="community_nsfw"><span class='badge'>NSFW</span></figcaption>
							</figure>
							<div class="community-info" v-if="community_title || loading">
								<h3 v-html="community_title" :class="{ skeleton: loading }"></h3>
								<p class="description" :class="{ skeleton: loading }" v-html="community_description"></p>
							</div>
						</a>
						<div class="rss-url" v-if="!loading && community_url && showRssURL">
							<h3>RSS Feed URL</h3>
							<p v-html="rssURL"></p>
						</div>
						<button v-else-if="!loading && community_url && !showRssURL" class="button copy-rss-url" :disabled="error || loading" @click="copyRSSurl($event)">
							<template v-if="copied">
								<svg class="icon icon-clipboard-copied">
									<use xlink:href="#icon-clipboard-copied"></use>
								</svg> URL copied to clipboard
							</template>
							<template v-else>
								<svg class="icon icon-copy">
									<use xlink:href="#icon-copy"></use>
								</svg> Copy RSS URL
							</template>
						<button v-if="!demoMode" class="clear-cache" @click="clearCache" :class="loading"><svg class="icon icon-cycle">
								<use xlink:href="#icon-cycle"></use>
							</svg> Refresh cache ({{ cacheSize }})</button>
					</div>
				</div>
			</section>

		</div>


		<!-- Footer -->
		<footer>
			<p class="repo-name">Upvote RSS v<?php echo UPVOTE_RSS_VERSION; ?></p>
			<p class="repo-link"><a href="https://github.com/johnwarne/upvote-rss/" target="_blank"><svg class="icon icon-github"><use xlink:href="#icon-github"></use></svg>GitHub</a></p>
			<form class="dark-mode" @submit.prevent>
				<label for="auto">
					<input type="radio" id="auto" name="mode" value="auto" v-model="darkMode">
					<span class="sr-only">Auto</span>
					<svg class="icon icon-schedule">
						<use xlink:href="#icon-schedule"></use>
					</svg>
				</label>
				<label for="light">
					<input type="radio" id="light" name="mode" value="light" v-model="darkMode">
					<span class="sr-only">Light</span>
					<svg class="icon icon-light-mode">
						<use xlink:href="#icon-light-mode"></use>
					</svg>
				</label>
				<label for="dark">
					<input type="radio" id="dark" name="mode" value="dark" v-model="darkMode">
					<span class="sr-only">Dark</span>
					<svg class="icon icon-dark-mode">
						<use xlink:href="#icon-dark-mode"></use>
					</svg>
				</label>
			</form>
		</footer>


	</main><!-- /app -->


	<!-- Send variables to javascript -->
	<script>
	const platform = '<?php echo PLATFORM; ?>';
	const defaultPlatform = '<?php echo DEFAULT_PLATFORM; ?>';
	const demoMode = <?php echo DEMO_MODE ? 'true' : 'false'; ?>;
	const redditEnabled = '<?php echo REDDIT_USER && REDDIT_CLIENT_ID && REDDIT_CLIENT_SECRET; ?>';
	const redditDefaultDomain = '<?php echo REDDIT_DEFAULT_DOMAIN; ?>';
	const redditDefaultDomainOverride = '<?php echo REDDIT_DEFAULT_DOMAIN_OVERRIDE; ?>';
	const redditDomain = '<?php echo REDDIT_DOMAIN; ?>';
	const overrideRedditDomain = <?php echo REDDIT_DEFAULT_DOMAIN === REDDIT_DOMAIN ? "false" : "true"; ?>;
	const subreddit = '<?php echo SUBREDDIT; ?>';
	const instance = '<?php echo INSTANCE; ?>';
	const instanceHackerNewsDefault = '<?php echo DEFAULT_HACKER_NEWS_INSTANCE; ?>';
	const instanceLemmyDefault = '<?php echo DEFAULT_LEMMY_INSTANCE; ?>';
	const instanceLobstersDefault
		= '<?php echo DEFAULT_LOBSTERS_INSTANCE; ?>';
	const instanceMbinDefault = '<?php echo DEFAULT_MBIN_INSTANCE; ?>';
	const instancePieFedDefault = '<?php echo DEFAULT_PIEFED_INSTANCE; ?>';
	const community = '<?php echo COMMUNITY; ?>';
	const communityType = '<?php echo COMMUNITY_TYPE; ?>';
	const communityHackerNewsDefault = '<?php echo DEFAULT_HACKER_NEWS_COMMUNITY; ?>';
	const communityLemmyDefault = '<?php echo DEFAULT_LEMMY_COMMUNITY; ?>';
	const communityLobstersDefault = '<?php echo DEFAULT_LOBSTERS_COMMUNITY; ?>';
	const communityLobstersDefaultCategory = '<?php echo DEFAULT_LOBSTERS_CATEGORY; ?>';
	const communityLobstersDefaultTag = '<?php echo DEFAULT_LOBSTERS_TAG; ?>';
	const communityMbinDefault = '<?php echo DEFAULT_MBIN_COMMUNITY; ?>';
	const communityPieFedDefault = '<?php echo DEFAULT_PIEFED_COMMUNITY; ?>';
	const scoreFilterAvailablePlatforms = <?php echo json_encode(SCORE_FILTER_AVAILABLE_PLATFORMS); ?>;
	const thresholdFilterAvailablePlatforms = <?php echo json_encode(THRESHOLD_FILTER_AVAILABLE_PLATFORMS); ?>;
	const averagePostsPerDayFilterAvailablePlatforms = <?php echo json_encode(AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE_PLATFORMS); ?>;
	const filterType = '<?php echo FILTER_TYPE; ?>';
	const score = <?php echo SCORE; ?>;
	const scoreDefaultLemmy = '<?php echo DEFAULT_LEMMY_SCORE; ?>';
	const scoreDefaultHackerNews = '<?php echo DEFAULT_HACKER_NEWS_SCORE; ?>';
	const scoreDefaultLobsters = '<?php echo DEFAULT_LOBSTERS_SCORE; ?>';
	const scoreDefaultMbin = '<?php echo DEFAULT_MBIN_SCORE; ?>';
	const scoreDefaultPieFed = '<?php echo DEFAULT_PIEFED_SCORE; ?>';
	const scoreDefaultReddit = '<?php echo DEFAULT_REDDIT_SCORE; ?>';
	const percentage = <?php echo PERCENTAGE; ?>;
	const averagePostsPerDay = <?php echo POSTS_PER_DAY; ?>;
	const showScore = <?php echo SHOW_SCORE ? "true" : "false"; ?>;
	const includeContent = <?php echo INCLUDE_CONTENT ? "true" : "false"; ?>;
	const summaryEnabled = <?php echo SUMMARY_ENABLED ? "true" : "false"; ?>;
	const includeSummary = <?php echo INCLUDE_SUMMARY ? "true" : "false"; ?>;
	const includeComments = <?php echo INCLUDE_COMMENTS ? "true" : "false"; ?>;
	const comments = <?php echo COMMENTS; ?>;
	const pinnedCommentsFilterAvailablePlatforms = <?php echo json_encode(PINNED_COMMENTS_AVAILABLE_PLATFORMS); ?>;
	const filterPinnedComments = <?php echo FILTER_PINNED_COMMENTS ? "true" : "false"; ?>;
	const filterNSFW = <?php echo FILTER_NSFW ? "true" : "false"; ?>;
	const blurNSFW = <?php echo BLUR_NSFW ? "true" : "false"; ?>;
	const filterOldPosts = <?php echo FILTER_OLD_POSTS ? "true" : "false"; ?>;
	const postCutoffDays = <?php echo POST_CUTOFF_DAYS; ?>;
	const cacheSize = '<?php echo getCacheSize(); ?>';
	<?php include 'js/lib/vue.global.prod.js'; ?>
	<?php include 'js/script.js'; ?>
	</script>

	<!-- SVGs -->
	<?php include 'inc/svgs.php'; ?>

</body>

</html>