customElements.define('upvote-rss', class extends HTMLElement {

	// Add static property sets for efficient lookups
	static booleanAttributes = new Set([
		'average-posts-per-day-filter-available', 'blur-nsfw', 'comments-available', 'demo-mode', 'filter-nsfw', 'filter-old-posts', 'filter-pinned-comments', 'include-comments', 'include-content', 'include-summary', 'loading', 'override-reddit-domain', 'pinned-comments-filter-available', 'reddit-enabled', 'score-filter-available', 'show-rss-url', 'show-score', 'summary-enabled', 'threshold-filter-available'
	]);
	static integerAttributes = new Set([
		'average-posts-per-day', 'comments', 'post-cutoff-days', 'score', 'score-default-github', 'score-default-hacker-news', 'score-default-lemmy', 'score-default-lobsters', 'score-default-mbin', 'score-default-piefed', 'score-default-reddit', 'threshold-percentage'
	]);

	// Constructor
	constructor () {
		super();

		// Set initial attributes
		this.setAttribute('dark-mode', localStorage.getItem('dark-mode') || this.attr('dark-mode') || 'auto');
		this.setAttribute('show-rss-url', navigator.clipboard ? false : true);
	}


	/**
	 * Called when the element is added to the DOM
	 * @returns {void}
	 * */
	connectedCallback () {
		// Set attributes
		this._progress = 1;
		this.error = false;
		this.posts = [];
		this.subreddits = [];
		this.community_icon = null;
		this.community_url = '#';
		this.community_title = 'Community Title';
		this.community_description = 'Community description';
		this.community_nsfw = null;
		this.platform_icon = null;
		this.progressRadius = 80;
		this.progressStroke = 10;
		this.progressNormalizedRadius = this.progressRadius - this.progressStroke * 2;
		this.progressCircumference = this.progressNormalizedRadius * 2 * Math.PI;
		this.progressStrokeDashOffset = 0;
		this.eventSource = null;

		// Skeleton debounce state
		this.allowSkeletons = false;
		this.skeletonDelay = 150;

		// Initial data fetching
		this.debouncedSearch();

		// Set up event listeners
		this.setupEventListeners();
	}


	// Getters for commonly used elements, with caching
	get skipLink() {
		return this._skipLink ??= document.querySelector('.skip-link');
	}
	get form() {
		return this._form ??= this.querySelector('form');
	}
	get submitButton() {
		return this._submitButton ??= this.querySelector('button[type="submit"]');
	}
	get step1() {
		return this._step1 ??= this.querySelector('.step-1');
	}
	get step2() {
		return this._step2 ??= this.querySelector('.step-2');
	}
	get postList() {
		return this._postList ??= this.querySelector('.post-list');
	}
	get postListPosts() {
		return this._postListPosts ??= this.querySelector('.post-list-posts');
	}
	get platformInput() {
		return this._platformInput ??= this.querySelector('[name="platform"]');
	}
	get subredditInput() {
		return this._subredditInput ??= this.querySelector('[name="subreddit"]');
	}
	get instanceInput() {
		return this._instanceInput ??= this.querySelector('[name="instance"]');
	}
	get communityInputs() {
		return this._communityInputs ??= this.querySelectorAll('[name="community"]');
	}
	get communitySelects() {
		return this._communitySelects ??= this.querySelectorAll('[name="community"]');
	}
	get typeInput() {
		return this._typeInput ??= this.querySelector('[name="type"]');
	}
	get languageInput() {
		return this._languageInput ??= this.querySelector('[name="language"]');
	}
	get topicInput() {
		return this._topicInput ??= this.querySelector('[name="topic"]');
	}
	get categoryInput() {
		return this._categoryInput ??= this.querySelector('[name="category"]');
	}
	get tagInput() {
		return this._tagInput ??= this.querySelector('[name="tag"]');
	}
	get filterTypeInput() {
		return this._filterTypeInput ??= this.querySelector('[name="filter-type"]');
	}
	get scoreInput() {
		return this._scoreInput ??= this.querySelector('[name="score"]');
	}
	get thresholdPercentageInput() {
		return this._thresholdPercentageInput ??= this.querySelector('[name="threshold-percentage"]');
	}
	get averagePostsPerDayInput() {
		return this._averagePostsPerDayInput ??= this.querySelector('[name="average-posts-per-day"]');
	}
	get overrideRedditDomainCheckbox() {
		return this._overrideRedditDomainCheckbox ??= this.querySelector('[type="checkbox"][name="override-reddit-domain"]');
	}
	get redditDomainInput() {
		return this._redditDomainInput ??= this.querySelector('[name="reddit-domain"]');
	}
	get showScoreCheckbox() {
		return this._showScoreCheckbox ??= this.querySelector('[type="checkbox"][name="show-score"]');
	}
	get includeContentCheckbox() {
		return this._includeContentCheckbox ??= this.querySelector('[type="checkbox"][name="include-content"]');
	}
	get includeSummaryCheckbox() {
		return this._includeSummaryCheckbox ??= this.querySelector('[type="checkbox"][name="include-summary"]');
	}
	get includeCommentsCheckbox() {
		return this._includeCommentsCheckbox ??= this.querySelector('[type="checkbox"][name="include-comments"]');
	}
	get commentsInput() {
		return this._commentsInput ??= this.querySelector('[name="comments"]');
	}
	get filterPinnedCommentsCheckbox() {
		return this._filterPinnedCommentsCheckbox ??= this.querySelector('[type="checkbox"][name="filter-pinned-comments"]');
	}
	get filterOldPostsCheckbox() {
		return this._filterOldPostsCheckbox ??= this.querySelector('[type="checkbox"][name="filter-old-posts"]');
	}
	get postCutoffDaysInput() {
		return this._postCutoffDaysInput ??= this.querySelector('[name="post-cutoff-days"]');
	}
	get filterNsfwCheckbox() {
		return this._filterNsfwCheckbox ??= this.querySelector('[type="checkbox"][name="filter-nsfw"]');
	}
	get blurNsfwCheckbox() {
		return this._blurNsfwCheckbox ??= this.querySelector('[type="checkbox"][name="blur-nsfw"]');
	}
	get darkModeRadios() {
		return this._darkModeRadios ??= this.querySelectorAll('[type="radio"][name="mode"]');
	}
	get clearCacheButton() {
		return this._clearCacheButton ??= this.querySelector('button.clear-cache');
	}
	get copyRssUrlButton() {
		return this._copyRssUrlButton ??= this.querySelector('.button.copy-rss-url');
	}
	get filterTypeLabelButton() {
		return this._filterTypeLabelButton ??= this.querySelector('label[for="filter-type"] span');
	}
	get filterInformation() {
		return this._filterInformation ??= this.querySelector('.filter-information');
	}
	get fiddlyBitsFieldset() {
		return this._fiddlyBitsFieldset ??= this.querySelector('fieldset.fiddly-bits');
	}
	get fiddlyBitsLegendSpan() {
		return this._fiddlyBitsLegendSpan ??= this.querySelector('.fiddly-bits legend span');
	}
	get filterTypeScoreOption() {
		return this._filterTypeScoreOption ??= this.querySelector('[name="filter-type"] option[value="score"]');
	}
	get filterTypeThresholdOption() {
		return this._filterTypeThresholdOption ??= this.querySelector('[name="filter-type"] option[value="threshold"]');
	}
	get filterTypeAveragePostsPerDayOption() {
		return this._filterTypeAveragePostsPerDayOption ??= this.querySelector('[name="filter-type"] option[value="averagePostsPerDay"]');
	}
	get filterOldPostsLabel() {
		return this._filterOldPostsLabel ??= this.querySelector('label[for="filter-old-posts"]');
	}
	get communityInfo() {
		return this._communityInfo ??= this.querySelector('.community-info');
	}


	/**
	 * Set up all event listeners for form elements and other interactive elements
	 * @returns {void}
	 */
	setupEventListeners() {
		// Delegated event listener for all form inputs
		this.form.addEventListener('input', (event) => {
			const { target } = event;
			const { name, type, checked, value } = target;

			// Handle checkboxes
			if (type === 'checkbox') {
				this.setAttribute(name, checked);
				return;
			}

			// Handle other inputs
			this.setAttribute(name === 'subreddit' ? 'subreddit' :
											 name === 'instance' ? 'instance' :
											 name === 'community' ? 'community' :
											 name === 'language' ? 'language' :
											 name === 'topic' ? 'topic' :
											 name === 'category' ? 'community' :
											 name === 'tag' ? 'community' :
											 name === 'reddit-domain' ? 'reddit-domain' :
											 name, value);
		});

		// Delegated event listener for change events (selects, radios)
		this.form.addEventListener('change', (event) => {
			const { target } = event;
			const { name, value } = target;

			if (name === 'platform') {
				this.setAttribute('platform', value);
			} else if (name === 'type') {
				this.setAttribute('community-type', value);
			} else if (name === 'filter-type') {
				this.setAttribute('filter-type', value);
			} else if (name === 'community') {
				this.setAttribute('community', value);
			} else if (name === 'mode') {
				this.setAttribute('dark-mode', value);
			}
		});

		// Form submission
		this.form.addEventListener('submit', (event) => {
			event.preventDefault();
			this.debouncedSearch();
			this.postList.scrollTo(0, 0);
			if(window.innerWidth < 992) {
				window.scrollTo({
					top: this.step2.offsetTop - 30,
					behavior: 'smooth'
				});
			}
		});

		// Non-form event listeners
		this.skipLink.addEventListener('click', (event) => {
			event.preventDefault();
			this.postList.scrollTo(0, 0);
			const firstElement = this.step1.querySelector('input, select, textarea');
			if (firstElement) {
				firstElement.focus();
			}
		});
		this.darkModeRadios.forEach((radio) => {
			radio.addEventListener('input', (event) => {
				this.setAttribute('dark-mode', event.target.value);
			});
		});
		this.clearCacheButton.addEventListener('click', (event) => {
			event.preventDefault();
			this.clearCache();
		});
		this.copyRssUrlButton.addEventListener('click', (event) => {
			event.preventDefault();
			this.copyRSSurl(event);
		});
		this.submitButton.addEventListener('click', (event) => {
			event.preventDefault();
			this.debouncedSearch();
		});
		this.filterTypeLabelButton.addEventListener('click', (event) => {
			const open = event.target.getAttribute('aria-expanded') === 'true' ? 'false' : 'true';
			event.target.setAttribute('aria-expanded', open);
		});
		this.filterTypeLabelButton.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				const open = this.filterTypeLabelButton.getAttribute('aria-expanded') === 'true' ? 'false' : 'true';
				this.filterTypeLabelButton.setAttribute('aria-expanded', open);
			}
		});
		this.fiddlyBitsLegendSpan.addEventListener('click', (event) => {
			event.preventDefault();
			const open = this.fiddlyBitsLegendSpan.getAttribute('aria-expanded') === 'true' ? 'false' : 'true';
			this.fiddlyBitsLegendSpan.setAttribute('aria-expanded', open);
		});
		this.fiddlyBitsLegendSpan.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				const open = this.fiddlyBitsLegendSpan.getAttribute('aria-expanded') === 'true' ? 'false' : 'true';
				this.fiddlyBitsLegendSpan.setAttribute('aria-expanded', open);
			}
		});
	}


	/**
	 * Called when an observed attribute is added, removed, or changed
	 * @param  {String} name     The attribute name
	 * @param  {String} oldValue The old attribute value
	 * @param  {String} newValue The new attribute value
	 */
	static observedAttributes = [
		'average-posts-per-day', 'blur-nsfw', 'cache-size', 'comments', 'comments-available', 'community', 'community-type', 'dark-mode', 'filter-nsfw', 'filter-old-posts', 'filter-pinned-comments', 'filter-type', 'include-comments', 'include-content', 'include-summary', 'instance', 'language', 'loading', 'override-reddit-domain', 'platform', 'post-cutoff-days', 'reddit-domain', 'rss-url', 'score', 'show-score', 'subreddit', 'threshold-percentage', 'topic'
	];
	attributeChangedCallback (name, oldValue, newValue) {
		// if (oldValue === null || oldValue === '') return;
		// console.log(`Attribute changed: ${name}, Old value: ${oldValue}, New value: ${newValue}`);

		// Loading
		if (name === 'loading') {
			if (this.attr('loading')) {
				this.postListPosts.setAttribute('aria-busy', 'true');
				this.clearCacheButton.disabled = true;
				// Debounce showing skeletons
				clearTimeout(this.skeletonDelayTimer);
				this.allowSkeletons = false;
				this.skeletonDelayTimer = setTimeout(() => {
					this.allowSkeletons = true;
					if (this.attr('loading')) {
						this.renderPostList();
						this.getCommunityInfo();
					}
				}, this.skeletonDelay || 150);
			} else {
				this.postListPosts.removeAttribute('aria-busy');
				this.stopProgressSSE();
				this.clearCacheButton.disabled = false;
				if (this.attr('demo-mode')) {
					this.clearCacheButton.hidden = true;
				}
				// Cancel skeleton debounce and reset
				clearTimeout(this.skeletonDelayTimer);
				this.allowSkeletons = false;
			}
			return;
		}

		// Platform
		if (name === 'platform') {
			const scoreSet = this.availablePlatformSet('score-filter-available-platforms');
			if (scoreSet.has(newValue)) {
				this.setAttribute('score-filter-available', true);
				this.filterTypeScoreOption.hidden = false;
			} else {
				this.setAttribute('score-filter-available', false);
				this.filterTypeScoreOption.hidden = true;
			}
			const thresholdSet = this.availablePlatformSet('threshold-filter-available-platforms');
			if (thresholdSet.has(newValue)) {
				this.setAttribute('threshold-filter-available', true);
				this.filterTypeThresholdOption.hidden = false;
			} else {
				this.setAttribute('threshold-filter-available', false);
				this.filterTypeThresholdOption.hidden = true;
			}
			const avgSet = this.availablePlatformSet('average-posts-per-day-filter-available-platforms');
			if (avgSet.has(newValue)) {
				this.setAttribute('average-posts-per-day-filter-available', true);
				this.filterTypeAveragePostsPerDayOption.hidden = false;
			} else {
				this.setAttribute('average-posts-per-day-filter-available', false);
				this.filterTypeAveragePostsPerDayOption.hidden = true;
			}
			const commentsSet = this.availablePlatformSet('comments-available-platforms');
			if (commentsSet.has(newValue)) {
				this.setAttribute('comments-available', true);
			} else {
				this.setAttribute('comments-available', false);
			}
			const pinnedSet = this.availablePlatformSet('pinned-comments-filter-available-platforms');
			if (pinnedSet.has(newValue)) {
				this.setAttribute('pinned-comments-filter-available', true);
			} else {
				this.setAttribute('pinned-comments-filter-available', false);
			}
			this.setAttribute('community-type', '');
			if(newValue === 'github') {
				this.setAttribute('community', this.attr('community-github-default'));
				this.querySelector('label[for="include-content"]').textContent = "Include README content";
				if (this._language) {
					this.setAttribute('language', this._language);
				}
				if (this._topic) {
					this.setAttribute('topic', this._topic);
				}
				this.setAttribute('score', this.attr('score-default-github'));
			} else {
				this.querySelector('label[for="include-content"]').textContent = "Include article content";
			}
			if(oldValue !== null && newValue === 'hacker-news') {
				this.setAttribute('instance', this.attr('instance-hacker-news-default'));
				this.setAttribute('community', this.attr('community-hacker-news-default'));
				this.setAttribute('filter-type', 'averagePostsPerDay');
				this.setAttribute('score', this.attr('score-default-hacker-news'));
			}
			if(oldValue !== null && newValue === 'lemmy') {
				this.setAttribute('instance', this.attr('instance-lemmy-default'));
				this.setAttribute('community', this.attr('community-lemmy-default'));
				this.setAttribute('score', this.attr('score-default-lemmy'));
				this.nextFrame(() => {
					this.querySelector('.lemmy [name="community"]').focus();
				});
			}
			if(oldValue !== null && newValue === 'lobsters') {
				this.setAttribute('instance', this.attr('instance-lobsters-default'));
				this.setAttribute('community', this.attr('community-lobsters-default'));
				this.setAttribute('filter-type', 'score');
				this.setAttribute('score', this.attr('score-default-lobsters'));
				if (this._lobstersCommunityType === null || this._lobstersCommunityType === '' || typeof this._lobstersCommunityType === 'undefined') {
					this._lobstersCommunityType = 'all';
				}
				this.setAttribute('community-type', this._lobstersCommunityType);
				if(this.attr('community-type') === 'all') {
					this.querySelector('.lobsters [name="type"]').value = this.attr('community-lobsters-default');
					this.setAttribute('community', this.attr('community-lobsters-default'));
				}
				if(this.attr('community-type') === 'category') {
					this.setAttribute('community', this.attr('community-lobsters-default-category'));
					this.setAttribute('community-type', 'category');
				}
				if(this.attr('community-type') === 'tag') {
					this.setAttribute('community', this.attr('community-lobsters-default-tag'));
					this.setAttribute('community-type', 'tag');
				}
			}
			if(oldValue !== null && newValue === 'mbin') {
				this.setAttribute('instance', this.attr('instance-mbin-default'));
				this.setAttribute('community', this.attr('community-mbin-default'));
				this.setAttribute('score', this.attr('score-default-mbin'));
				this.nextFrame(() => {
					this.querySelector('.mbin [name="community"]').focus();
				});
			}
			if(oldValue !== null && newValue === 'piefed') {
				this.setAttribute('instance', this.attr('instance-piefed-default'));
				this.setAttribute('community', this.attr('community-piefed-default'));
				this.setAttribute('score', this.attr('score-default-piefed'));
				this.nextFrame(() => {
					this.querySelector('.piefed [name="community"]').focus();
				});
			}
			if(oldValue !== null && newValue !== 'reddit') {
				this.setAttribute('override-reddit-domain', false);
			}
			if(oldValue !== null && newValue === 'reddit') {
				this.setAttribute('instance', this.attr('instance-reddit-default'));
				this.setAttribute('subreddit', this.attr('subreddit') || this.attr('community-reddit-default'));
				this.setAttribute('community', this.attr('subreddit') || this.attr('community-reddit-default'));
				this.setAttribute('score', this.attr('score-default-reddit'));
				this.nextFrame(() => {
					this.subredditInput.focus();
				});
			}
		}

		// Instance
		if (name === 'instance') {
			// Normalize instance URL
			if(this.attr('instance').includes('://')) {
				this.setAttribute('instance', this.attr('instance').replace(/(^\w+:|^)\/\//, '').replace(/\/$/, ''));
			}
			if(this.attr('instance').endsWith('/')) {
				this.setAttribute('instance', this.attr('instance').slice(0, -1));
			}
			if(this.attr('instance').trim() !== this.attr('instance')) {
				this.setAttribute('instance', this.attr('instance').trim());
			}
			this.instanceInput.value = this.attr('instance');
		}

		// Community
		if (name === 'community') {
			this.querySelectorAll('[name="community"], [name="category"], [name="tag"]').forEach(input => {
				if (input.id === 'hacker-news-type' && this.attr('platform') === 'hacker-news') {
					input.value = this.attr('community');
				} else if (input.id === 'community' && (this.attr('platform') === 'lemmy' || this.attr('platform') === 'piefed')) {
					input.value = this.attr('community');
				} else if (input.id === 'community-mbin' && this.attr('platform') === 'mbin') {
					input.value = this.attr('community');
				} else {
					return;
				}
			});
		}

		// Community Type
		if (name === 'community-type' && newValue !== null && newValue !== '') {
			this._lobstersCommunityType = newValue;
			this.querySelector('[name="type"]').value = this.attr('community-type');
			if(this.attr('platform') === 'lobsters' && newValue === 'category') {
				this.setAttribute('community', this.attr('community-lobsters-default-category'));
				this.nextFrame(() => {
					this.categoryInput.value = this.attr('community');
					this.categoryInput.focus();
				});
			}
			if(this.attr('platform') === 'lobsters' && newValue === 'tag') {
				this.setAttribute('community', this.attr('community-lobsters-default-tag'));
				this.nextFrame(() => {
					this.tagInput.value = this.attr('community');
					this.tagInput.focus();
				});
			}
		}

		// Language
		if (name === 'language' && this.attr('platform') === 'github') {
			this._language = newValue;
			this.scheduleSearchAfterAttributeBatch();
		}

		// Topic
		if (name === 'topic' && this.attr('platform') === 'github') {
			this._topic = newValue;
			this.scheduleSearchAfterAttributeBatch();
		}

		// Filter type
		if (name === 'filter-type') {
			this.filterTypeInput.value = this.attr('filter-type');
			this.nextFrame(() => {
				this.renderFilterTypeHelpInfo();
			});
		}

		// Score
		if (name === 'score') {
			this.scoreInput.value = this.attr('score');
			this.nextFrame(() => {
				this.renderFilterTypeHelpInfo();
			});
		}

		// Threshold
		if (name === 'threshold-percentage') {
			this.thresholdPercentageInput.value = this.attr('threshold-percentage');
			this.nextFrame(() => {
				this.renderFilterTypeHelpInfo();
			});
		}

		// Posts Per Day
		if (name === 'average-posts-per-day') {
			this.averagePostsPerDayInput.value = this.attr('average-posts-per-day');
			this.nextFrame(() => {
				this.renderFilterTypeHelpInfo();
			});
		}

		// Override Reddit domain
		if (name === 'override-reddit-domain') {
			if (this.attr('override-reddit-domain')) {
				this.setAttribute('reddit-domain', this.attr('reddit-default-domain-override'));
				this.nextFrame(() => {
					this.redditDomainInput.focus();
				});
			} else {
				this.setAttribute('reddit-domain', this.attr('reddit-default-domain'));
			}
		}

		// Subreddit
		if (name === 'subreddit' && this.attr('platform') === 'reddit') {
			this.subredditInput.value = this.attr('subreddit');
			this.getSubreddits();
		}

		// Show score
		if (name === 'show-score') {
			this.querySelector('[name="show-score"]').checked = this.attr('show-score');
			this.updateURL();
			return;
		}

		// Include content
		if (name === 'include-content') {
			this.includeContentCheckbox.checked = this.attr('include-content');
			this.updateURL();
			return;
		}

		// Include summary
		if (name === 'include-summary') {
			this.querySelector('[name="include-summary"]').checked = this.attr('include-summary');
			this.updateURL();
			return;
		}

		// Comments available
		if (name === 'comments-available') {
			if (!this.attr('comments-available')) {
				this.setAttribute('comments', 0);
				this.setAttribute('include-comments', false);
			}
			return;
		}

		// Include comments
		if (name === 'include-comments') {
			this.querySelector('[name="include-comments"]').checked = this.attr('include-comments');
			this.attr('include-comments') ? this.setAttribute('comments', 5) : this.setAttribute('comments', 0);
			this.updateURL();
			return;
		}

		// Comments
		if (name === 'comments') {
			this.commentsInput.value = this.attr('comments');
			this.updateURL();
			return;
		}

		// Filter pinned comments
		if (name === 'filter-pinned-comments') {
			this.querySelector('[name="filter-pinned-comments"]').checked = this.attr('filter-pinned-comments');
			this.updateURL();
			return;
		}

		// Filter old posts
		if (name === 'filter-old-posts') {
			this.filterOldPostsCheckbox.checked = this.attr('filter-old-posts');
			if(this.attr('post-cutoff-days') === 0) {
				this.setAttribute('post-cutoff-days', 7);
			}
			this.renderFilterOldPostsLabel();
		}

		// Post cutoff days
		if (name === 'post-cutoff-days') {
			this.postCutoffDaysInput.value = this.attr('post-cutoff-days');
			this.renderFilterOldPostsLabel();
		}

		// RSS URL
		if (name === 'rss-url') {
			this.querySelector('.rss-url p').textContent = this.attr('rss-url');
			return;
		}

		// Cache size
		if (name === 'cache-size') {
			this.querySelector('.clear-cache span span').textContent = this.attr('cache-size');
			return;
		}

		// Dark mode
		if (name === 'dark-mode') {
			this.querySelectorAll('[name="mode"]').forEach(input => {
				if (input.value === this.attr('dark-mode')) {
					input.checked = true;
				}
			});
			document.documentElement.dataset.colorScheme = this.attr('dark-mode');
			localStorage.setItem('dark-mode', this.attr('dark-mode'));
			return;
		}

		// Run the search
		if (oldValue !== newValue &&
			newValue !== false &&
			newValue !== null &&
			newValue !== ''
		) {
			// Batch attribute reactions so a cascade of setAttribute calls only triggers one search
			this.scheduleSearchAfterAttributeBatch();
		}
	}


	/**
	 * Get or set the progress of the element
	 * @returns {number} - The progress value
	 * */
	get progress() {
		return this._progress;
	}
	set progress(value) {
		this._progress = value;
		this.updateProgressIndicator();
	}


	/**
	 * Get the value of an attribute with proper type conversion
	 * @param {string} attribute - The name of the attribute
	 * @returns {string|boolean|number} - The value of the attribute with correct type
	 * */
	attr (attribute) {
		const attrValue = this.getAttribute(attribute);

		if (this.constructor.booleanAttributes.has(attribute)) {
			return attrValue === 'true' || attrValue === '' ? true : false;
		}

		if (this.constructor.integerAttributes.has(attribute)) {
			return parseInt(attrValue) || 0;
		}

		return attrValue;
	}


	/**
	 * Get (and cache) a Set of platforms from a CSV attribute like "score-filter-available-platforms"
	 * @param {string} attribute - attribute name ending with -available-platforms
	 * @returns {Set<string>} Set of platform tokens
	 */
	availablePlatformSet(attribute) {
		this._availablePlatformSets ??= {};
		if (!this._availablePlatformSets[attribute]) {
			const raw = this.getAttribute(attribute) || '';
			let list = [];
			if (raw.startsWith('[')) { // JSON encoded array
				try {
					const parsed = JSON.parse(raw);
					if (Array.isArray(parsed)) {
						list = parsed;
					}
				} catch (e) {
					// fallback to CSV parsing
					list = raw.split(',');
				}
			} else {
				list = raw.split(',');
			}
			this._availablePlatformSets[attribute] = new Set(
				list
					.map(v => typeof v === 'string' ? v.trim().replace(/^"|"$/g, '').replace(/^'|'$/g, '') : v)
					.filter(v => typeof v === 'string' && v.length)
			);
		}
		return this._availablePlatformSets[attribute];
	}


	/**
	 * Perform a debounced search to get posts
	 * @param {number} delay - The delay in milliseconds
	 * @returns {void}
	 * */
	debouncedSearch(timeout) {
		const isATextFieldFocused = (document.activeElement.tagName === 'INPUT' && document.activeElement.type === 'text') || document.activeElement.tagName === 'TEXTAREA';
		const isANumberFieldFocused = document.activeElement.tagName === 'INPUT' && document.activeElement.type === 'number';
		if (!timeout && isANumberFieldFocused) {
			timeout = 300;
		} else if (!timeout && !isATextFieldFocused) {
			timeout = 2;
		} else if (!timeout) {
			timeout = 600;
		}
		clearTimeout(this.debounceTimer);
		this.debounceTimer = setTimeout(() => {
			this.getPosts();
		}, timeout);
	}


	/**
	 * Coalesce multiple synchronous attributeChangedCallback invocations
	 * into a single debouncedSearch scheduling using a microtask
	 * @returns {void}
	 */
	scheduleSearchAfterAttributeBatch() {
		// If a batch flush is already queued, just mark pending and return
		if (this._attributeBatchScheduled) {
			this._pendingAttributeSearch = true;
			return;
		}
		this._pendingAttributeSearch = true;
		this._attributeBatchScheduled = true;
		Promise.resolve().then(() => {
			this._attributeBatchScheduled = false;
			if (this._pendingAttributeSearch) {
				this._pendingAttributeSearch = false;
				this.debouncedSearch();
			}
		});
	}

	/**
	 * Schedule a callback on the next animation frame(s)
	 * @param {Function} callback - The callback to execute
	 * @param {number} frames - The number of frames to wait before executing the callback
	 * @returns {void}
	 */
	nextFrame(callback, frames = 1) {
		const step = () => {
			if (--frames <= 0) callback();
			else requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	}


	/**
	 * Update the URL with the current parameters
	 * (debounced to avoid excessive updates)
	 * @returns {void}
	 * */
	updateURL() {
		clearTimeout(this._updateURLTimer);
		this._updateURLTimer = setTimeout(() => {
			this._updateURLTimer = null;
			const newURL = new URL(window.location.href.split("?")[0]);
			newURL.searchParams.set('platform', this.attr('platform'));
			if(this.attr('platform') === 'reddit') {
				newURL.searchParams.set('subreddit', this.attr('subreddit'));
				if(this.attr('override-reddit-domain') && this.attr('reddit-domain') !== this.attr('reddit-default-domain')) {
					newURL.searchParams.set('redditDomain', this.attr('reddit-domain'));
				}
			}
			if(this.attr('platform') === 'github' && this.attr('language') !== '') {
				newURL.searchParams.set('language', this.attr('language'));
			}
			if(this.attr('platform') === 'github' && this.attr('topic') !== '') {
				newURL.searchParams.set('topic', this.attr('topic'));
			}
			if(this.attr('platform') === 'hacker-news') {
				newURL.searchParams.set('community', this.attr('community'));
			}
			if(this.attr('platform') === 'lemmy' || this.attr('platform') === 'mbin' || this.attr('platform') === 'piefed') {
				newURL.searchParams.set('instance', this.attr('instance'));
				newURL.searchParams.set('community', this.attr('community'));
			}
			if(this.attr('platform') === 'lobsters') {
				newURL.searchParams.set('community', this.attr('community'));
				newURL.searchParams.set('type', this.attr('community-type'));
			}
			if(this.attr('filter-type') === 'score') {
				newURL.searchParams.set('score', this.attr('score'));
			} else if(this.attr('filter-type') === 'threshold') {
				newURL.searchParams.set('threshold', this.attr('threshold-percentage'));
			} else if(this.attr('filter-type') === 'averagePostsPerDay') {
				newURL.searchParams.set('averagePostsPerDay', this.attr('average-posts-per-day'));
			}
			if(this.attr('show-score')) {
				newURL.searchParams.set('showScore', '');
			}
			if(this.attr('include-content')) {
				newURL.searchParams.set('content', '');
			} else {
				newURL.searchParams.set('content', '0');
			}
			if(this.attr('include-summary')) {
				newURL.searchParams.set('summary', '');
			}
			if(this.attr('comments') > 0) {
				newURL.searchParams.set('comments', this.attr('comments'));
			}
			if(this.attr('filter-pinned-comments')) {
				newURL.searchParams.set('filterPinnedComments', '');
			}
			if(this.attr('filter-nsfw')) {
				newURL.searchParams.set('filterNSFW', '');
			} else if(this.attr('blur-nsfw')) {
				newURL.searchParams.set('blurNSFW', '');
			}
			if(this.attr('filter-old-posts')) {
				newURL.searchParams.set('filterOldPosts', this.attr('post-cutoff-days'));
			}
			const url = newURL.toString().replace(/=&/g, '&').replace(/=$/g, '');
			history.replaceState(null, null, url);
			this.setAttribute('rss-url', url + '&view=rss');
		}, 10);
	}


	/**
	 * Get the posts from the API and update the post list
	 * @returns {void}
	 * */
	async getPosts() {
		this.progress = 1;
		this.nextFrame(() => {
			this.setAttribute('loading', true);
		});

		this.nextFrame(() => {
			this.renderPostList();
			this.getCommunityInfo();
		});

		clearTimeout(this.progressSSETimer);
		this.progressSSETimer = setTimeout(() => {
			if (this.attr('loading')) {
				this.startProgressSSE();
			}
		}, 650);

		// If in demo mode and platform is still reddit, reset to default platform
		if(this.attr('demo-mode') && this.attr('platform') === 'reddit') {
			this.setAttribute('platform', this.attr('default-platform'));
		}

		this._getPostsController?.abort();
		this._getPostsController = new AbortController();

		try {
			const response = await fetch('ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				signal: this._getPostsController.signal,
				body: JSON.stringify({
					getPosts: true,
					platform: this.attr('platform'),
					redditDomain: this.attr('reddit-domain'),
					subreddit: this.attr('subreddit'),
					instance: this.attr('instance'),
					community: this.attr('community'),
					communityType: this.attr('community-type'),
					language: this.attr('language'),
					topic: this.attr('topic'),
					filterType: this.attr('filter-type'),
					score: this.attr('score'),
					threshold: this.attr('threshold-percentage'),
					averagePostsPerDay: this.attr('average-posts-per-day'),
					includeComments: this.attr('include-comments'),
					comments: this.attr('comments'),
					filterPinnedComments: this.attr('filter-pinned-comments'),
					filterNSFW: this.attr('filter-nsfw'),
					blurNSFW: this.attr('blur-nsfw'),
					filterOldPosts: this.attr('filter-old-posts'),
					postCutoffDays: this.attr('post-cutoff-days'),
				})
			});

			if (!response.ok) {
				throw new Error(`Request failed with status ${response.status}`);
			}

			const json = await response.json();

			if (json.error) {
				this.posts = [];
				this.community_icon = null;
				this.community_url = '#';
				this.community_title = 'Community Title';
				this.community_description = 'Community description';
				this.community_nsfw = null;
				this.error = json.error;
				console.log(this.error);
			} else {
				const posts = json.filtered_posts;
				if (this.attr('reddit-default-domain') !== this.attr('reddit-domain')) {
					for (let i = 0; i < posts.length; i++) {
						posts[i].permalink = posts[i].permalink.replace(this.attr('reddit-default-domain'), this.attr('reddit-domain'));
					}
				}
				this.posts = posts;
				this.error = false;
				this.community_icon = json.community_icon;
				this.community_url = json.community_url;
				this.community_title = json.community_title;
				this.community_description = json.community_description;
				this.community_nsfw = json.community_nsfw;
				this.platform_icon = json.platform_icon;
				this.setAttribute('cache-size', json.cacheSize);
				this.updateURL();
				this.postList.scrollTo(0, 0);
			}
		} catch (err) {
			if (err.name === 'AbortError') {
				// Silent: a newer request started.
			} else {
				console.error(err);
				this.error = this.error || 'Failed to load posts.';
				this.posts = [];
			}
		} finally {
			this.setAttribute('loading', false);
			this.renderPostList();
			this.getCommunityInfo();
		}
	}


	/**
	 * Render the post list based on the current state
	 * @returns {void}
	 */
	renderPostList() {
		if (!this.postListPosts) return;

		// Build in a detached tree to minimize layout/reflow work
		const frag = document.createDocumentFragment();

		const createEl = (tag, className, text) => {
			const el = document.createElement(tag);
			if (className) el.className = className;
			if (text !== undefined) el.textContent = text;
			return el;
		};

		// Error state
		const hasExistingErrorMessage = !!this.postListPosts.querySelector('p.error');
		if (this.error && !(this.attr('loading') && hasExistingErrorMessage)) {
			const errorParagraph = createEl('p', 'error', this.error);
			errorParagraph.setAttribute('role', 'alert');
			frag.appendChild(errorParagraph);
			this.postListPosts.replaceChildren(frag);
			return;
		}

		// No posts found
		if (!this.attr('loading') && this.posts.length < 1) {
			frag.appendChild(createEl('p', 'no-posts-found', 'No posts found that match the filters.'));
			this.postListPosts.replaceChildren(frag);
			return;
		}

		// Skeleton/loading state
		if (this.attr('loading')) {
			// Skip rendering skeletons until the debounce delay has elapsed
			if (!this.allowSkeletons) {
				return;
			}
			// If we have posts from a previous load, show those as the basis for skeletons
			const posts = this.postListPosts.querySelectorAll('article');
			if (posts.length) {
				posts.forEach(post => {
					post.classList.add("loading");
					post.setAttribute("aria-hidden", "true");
					post.querySelectorAll('.thumbnail, h3, p, time, .score').forEach(el => el.classList.add('skeleton'));
				});
				return;
			}
			// Otherwise, show generic skeletons
			const numberOfSkeletons = 3;
			const usePosts = Array.from({ length: numberOfSkeletons }, (_, i) => ({
				title: 'Post title',
				relative_date: 'Time',
				time_rfc_822: new Date().toISOString(),
				score_formatted: 'Score',
				thumbnail_url: 'https://www.redditstatic.com/mweb2x/favicon/76x76.png'
			}));
			for (let i = 0; i < usePosts.length; i++) {
				const post = usePosts[i];
				const article = createEl('article', 'loading');
				article.setAttribute('aria-hidden', 'true');
				const a = createEl('a');
				a.href = '#';
				a.tabIndex = -1;
				if (post.thumbnail_url) {
					const thumb = createEl('div', 'thumbnail default-image skeleton');
						const img = createEl('img');
						img.src = 'https://www.redditstatic.com/mweb2x/favicon/76x76.png';
						img.alt = '';
						img.decoding = 'async';
						thumb.appendChild(img);
					a.appendChild(thumb);
				}
				const content = createEl('div', 'content');
				const h3 = createEl('h3', 'skeleton', post.title);
				const time = createEl('time', 'skeleton', post.relative_date);
				time.setAttribute('datetime', post.time_rfc_822);
				const score = createEl('div', 'score skeleton');
				const spanArrow = createEl('span');
				spanArrow.textContent = '⬆︎';
				spanArrow.setAttribute('aria-hidden', 'true');
				score.appendChild(spanArrow);
				score.appendChild(document.createTextNode(post.score_formatted));
				content.appendChild(h3);
				content.appendChild(time);
				content.appendChild(score);
				a.appendChild(content);
				article.appendChild(a);
				frag.appendChild(article);
			}
			this.postListPosts.replaceChildren(frag);
			return;
		}

		// Content state
		for (let i = 0; i < this.posts.length; i++) {
			const post = this.posts[i];
			const article = createEl('article');
			article.style.setProperty('--index', (i % 10));
			const a = createEl('a');
			a.href = post.permalink;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';

			// Thumbnail logic mirrors previous template string conditions
			const blurNsfw = !!this.attr('blur-nsfw');
			let thumbnailSrc = '';
			if (blurNsfw && post.nsfw && post.thumbnail_obfuscated_url) {
				thumbnailSrc = post.thumbnail_obfuscated_url;
			} else if ((!blurNsfw || (blurNsfw && !post.nsfw)) && post.thumbnail_url && post.thumbnail_url !== 'self' && post.thumbnail_url !== 'default') {
				thumbnailSrc = post.thumbnail_url;
			} else if (post.thumbnail_url && !thumbnailSrc) { // fallback if provided
				thumbnailSrc = post.thumbnail_url;
			} else if (this.community_icon) {
				thumbnailSrc = this.community_icon;
			}
			if (thumbnailSrc) {
				const thumbDiv = createEl('div', 'thumbnail');
				const img = createEl('img');
				img.src = thumbnailSrc;
				img.alt = '';
				img.decoding = 'async';
				thumbDiv.appendChild(img);
				a.appendChild(thumbDiv);
			}

			const content = createEl('div', 'content');
			const h3 = createEl('h3');
			h3.textContent = post.title;
			const time = createEl('time');
			time.setAttribute('datetime', post.time_rfc_822);
			time.textContent = post.relative_date;
			const score = createEl('div', 'score');
			const spanArrow = createEl('span');
			spanArrow.textContent = '⬆︎';
			spanArrow.setAttribute('aria-hidden', 'true');
			score.appendChild(spanArrow);
			score.appendChild(document.createTextNode(post.score_formatted));
			content.appendChild(h3);
			if (this.attr('platform') === 'github' && post.selftext_html) {
				const selftext = createEl('p', 'selftext');
				const tmpDiv = document.createElement('div');
				tmpDiv.innerHTML = post.selftext_html;
				let text = tmpDiv.textContent || tmpDiv.innerText || '';
				text = text.replace(/<br\s*\/?>/gi, ' ').replace(/<\/?[^>]+(>|$)/g, '').replace(/\s+/g, ' ').trim();
				selftext.innerText = text;
				content.appendChild(selftext);
			}
			content.appendChild(time);
			content.appendChild(score);
			a.appendChild(content);
			article.appendChild(a);
			frag.appendChild(article);
		}

		this.postListPosts.replaceChildren(frag);
	}


	/**
	 * Fetch subreddits based on the current subreddit
	 * @returns {void}
	 * */
	async getSubreddits() {
		const query = this.attr('subreddit') || '';
		// If too short, cancel pending work and abort any in-flight request
		if (query.length < 3) {
			clearTimeout(this._getSubredditsDebounce);
			this._getSubredditsController?.abort();
			return;
		}

		// Debounce successive keystrokes
		clearTimeout(this._getSubredditsDebounce);
		this._getSubredditsDebounce = setTimeout(async () => {
			try {
				// Abort previous fetch if still running
				this._getSubredditsController?.abort();
				this._getSubredditsController = new AbortController();
				const response = await fetch('ajax.php', {
					method: 'POST',
						headers: {
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					signal: this._getSubredditsController.signal,
					body: JSON.stringify({
						getSubreddits: true,
						subreddit: query,
					})
				});
				const json = await response.json();
				if (json.error) {
					this.error = json.error;
					console.log(this.error);
					return;
				} else if (Array.isArray(json.subreddits)) {
					const subredditSet = new Set(this.subreddits || []);
					for (let i = 0; i < json.subreddits.length; i++) {
						subredditSet.add(json.subreddits[i]['name']);
					}
					this.subreddits = Array.from(subredditSet).sort();
				}
				if (this.subreddits?.length) {
					const datalist = this.querySelector('datalist#subreddits');
					if (datalist) {
						datalist.replaceChildren(...this.subreddits.map(name => {
							const o = document.createElement('option');
							o.value = name;
							o.textContent = name;
							return o;
						}));
					}
				}
			} catch (e) {
				if (e.name !== 'AbortError') {
					console.log(e);
				}
			}
		}, 200);
	}


	/**
	 * Fetch community information based on the current community
	 * @returns {void}
	 * */
	async getCommunityInfo() {
		const container = this.communityInfo;
		if (!container) return;

		const frag = document.createDocumentFragment();
		const a = document.createElement('a');
		a.className = 'community';
		a.href = this.community_url;
		a.target = '_self';
		a.title = this.community_title || '';

		const isLoading = !!this.attr('loading');

		if (isLoading) {
			container.setAttribute('aria-busy', 'true');
		} else {
			container.removeAttribute('aria-busy');
		}

		// Figure / icon
		if (isLoading) {
			if (!this.allowSkeletons) {
				return;
			}
			const figure = document.createElement('figure');
			figure.className = 'community-image skeleton';
			const img = document.createElement('img');
			img.width = 60; img.height = 60;
			if (this.community_icon) {
				img.src = this.community_icon;
				img.alt = '';
				img.decoding = 'async';
			}
			figure.appendChild(img);
			if (this.community_icon && this.community_nsfw) {
				const figcap = document.createElement('figcaption');
				figcap.className = 'nsfw';
				const badge = document.createElement('span');
				badge.className = 'badge';
				badge.textContent = 'NSFW';
				figcap.appendChild(badge);
				figure.appendChild(figcap);
			}
			a.appendChild(figure);
		} else if (this.community_icon) {
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.title = (this.community_title || 'Community') + ' (opens in a new tab)';
			const figure = document.createElement('figure');
			figure.className = 'community-image';
			const img = document.createElement('img');
			img.src = this.community_icon;
			img.alt = '';
			img.width = 60; img.height = 60;
			img.decoding = 'async';
			figure.appendChild(img);
			if (this.community_nsfw) {
				const figcap = document.createElement('figcaption');
				figcap.className = 'nsfw';
				const badge = document.createElement('span');
				badge.className = 'badge';
				badge.textContent = 'NSFW';
				figcap.appendChild(badge);
				figure.appendChild(figcap);
			}
			a.appendChild(figure);
		}

		// Detail
		if (this.error) {
			container.replaceChildren();
			return;
		} else if (isLoading) {
			const detail = document.createElement('div');
			detail.className = this.community_title ? 'community-detail' : 'community-detail skeleton';
			const h3 = document.createElement('h3');
			h3.textContent = this.community_title || 'Community title';
			if (this.community_title) h3.classList.add('skeleton');
			const p = document.createElement('p');
			p.className = 'description' + (this.community_title ? ' skeleton' : '');
			p.textContent = this.community_description || 'Community description';
			detail.appendChild(h3);
			detail.appendChild(p);
			a.appendChild(detail);
		} else if (this.community_title) {
			const detail = document.createElement('div');
			detail.className = 'community-detail';
			const h3 = document.createElement('h3');
			h3.textContent = this.community_title;
			const p = document.createElement('p');
			p.className = 'description';
			p.textContent = this.community_description;
			detail.appendChild(h3);
			detail.appendChild(p);
			a.appendChild(detail);
		}

		frag.appendChild(a);
		container.replaceChildren(frag);
	}


	/**
	 * Clear the cache and reload posts
	 * @returns {void}
	 * */
	async clearCache() {
		if (this.attr('demo-mode')) {
			return;
		}
		this.postList.scrollTo(0, 0);
		if(window.innerWidth < 992) {
			window.scrollTo({
				top: this.step1.offsetTop - 30,
				behavior: 'smooth'
			});
		}
		const response = await fetch('ajax.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: JSON.stringify({
				clearCache: true,
			})
		});
		const json = await response.json();
		if (json.error) {
			this.error = json.error;
			console.log(this.error);
			return;
		} else {
			this.setAttribute('cache-size', json.cacheSize);
			this.getPosts();
		}
	}


	/**
	 * Toggle the filter information
	 * @returns {void}
	 * */
	toggleFilterInformation() {
		this.filterInformation.classList.toggle('open');
	}


	/**
	 * Render the filter old posts label
	 * @returns {void}
	 * */
	renderFilterOldPostsLabel() {
		if (this.attr('filter-old-posts') && this.attr('post-cutoff-days') > 0) {
			this.filterOldPostsLabel.innerHTML = this.attr('post-cutoff-days') > 1 ? '<span>Include posts from the past <span>' + this.attr('post-cutoff-days') + '</span> days</span>' : (this.attr('post-cutoff-days') === 1 ? '<span>Include posts from the past day</span>' : 'Filter old posts');
		} else {
			this.filterOldPostsLabel.innerHTML = 'Filter old posts';
		}
	}


	/**
	 * Render the filter type help information
	 * @returns {void}
	 * */
	renderFilterTypeHelpInfo() {
		let infoHTML = '<dl>';
		infoHTML += '<dt>Score</dt>';
		if (this.attr('filter-type') === 'score') {
			infoHTML += `<dd>Posts below the specified score (${this.attr('score')}) will be filtered out.</dd>`;
		} else {
			infoHTML += '<dd>Posts below the specified score will be filtered out.</dd>';
		}
		infoHTML += '<dt>Threshold</dt>';
		if (this.attr('filter-type') === 'threshold') {
			infoHTML += `<dd>Posts below the specified percentage (${this.attr('threshold-percentage')}%) of the community's monthly top posts' average score will be filtered out.</dd>`;
		} else {
			infoHTML += '<dd>Posts below the specified percentage of the community\'s monthly top posts\' average score will be filtered out.</dd>';
		}
		infoHTML += '<dt>Posts Per Day</dt>';
		if (this.attr('filter-type') === 'averagePostsPerDay') {
			infoHTML += `<dd>The RSS feed will output roughly the specified number of posts per day <span>(${this.attr('average-posts-per-day')})</span>.</dd>`;
		} else {
			infoHTML += '<dd>The RSS feed will output roughly the specified number of posts per day.</dd>';
		}
		infoHTML += '</dl>';
		this.filterInformation.innerHTML = infoHTML;
	}


	/**
	 * Render the progress indicator
	 * @returns {void}
	 * */
	updateProgressIndicator() {
		const progressIndicator = this.querySelector('progress-indicator');
		if (progressIndicator) {
			const progress = progressIndicator.querySelector('progress');
			if (progress) {
				progress.value = this.progress;
				progress.innerHTML = this.progress + '%';
			}
			const progressPercentage = progressIndicator.querySelector('progress-percentage');
			if (progressPercentage) {
				progressPercentage.textContent = this.progress + '%';
			}
			const circle = progressIndicator.querySelector('circle');
			if (circle) {
				circle.style.strokeDashoffset = `${this.progressCircumference - this.progress / 100 * this.progressCircumference}`;
			}
		}
	}


	/**
	 * Copy the RSS URL to the clipboard
	 * @param {Event} event - The event object
	 * @returns {void}
	 * */
	copyRSSurl(event) {
		const url = new URL(window.location.href);
		url.searchParams.delete('view');
		url.searchParams.set('view', 'rss');
		navigator.clipboard.writeText(url.href);
		let button = event.target.closest('button');
		button.querySelector('.button-text').innerHTML = '<svg class="icon icon-clipboard-copied"><use xlink:href="#icon-clipboard-copied"></use></svg> URL copied to clipboard';
		let span = document.createElement('span');
		span.classList.add('up-arrows');
		button.appendChild(span);
		let numberOfArrows = Math.floor(Math.random() * 7) + 5;
		for (let i = 0; i < numberOfArrows; i++) {
			let arrow = document.createElement('span');
			arrow.textContent = '⬆︎';
			arrow.classList.add('arrow');
			span.appendChild(arrow);
			let randomDelay = Math.floor(Math.random() * 300);
			let randomLeft = Math.floor(Math.random() * 90);
			let randomOpacity = Math.floor(Math.random() * 10) + 1;
			let scale = Math.random() * 0.6 + 0.4;
			arrow.style.setProperty('--delay', randomDelay + 'ms');
			arrow.style.setProperty('--left', randomLeft + '%');
			arrow.style.setProperty('--opacity', '0.' + randomOpacity);
			arrow.style.setProperty('--scale', scale);
		}
		this.nextFrame(() => {
			span.classList.add('animate');
		}, 2);
		setTimeout(() => {
			span.remove();
		}, 800);
		clearTimeout(this.copyTimeout);
		this.copyTimeout = setTimeout(() => {
			button.querySelector('.button-text').innerHTML = '<svg class="icon icon-copy"><use xlink:href="#icon-copy"></use></svg> Copy RSS URL';
		}, 1500);
	}


	/**
	 * Start the progress server-sent events (SSE)
	 * @returns {void}
	 * */
	startProgressSSE() {
		// Close any existing connection
		this.stopProgressSSE();

		const params = new URLSearchParams({
			platform: this.attr('platform'),
			instance: this.attr('instance'),
			community: this.attr('community'),
			subreddit: this.attr('subreddit')
		});

		if(typeof(EventSource) === "undefined") {
			console.warn('EventSource progress SSE not supported by this browser');
			return;
		}

		this.progress = 1;
		const eventSourceURL = `progress-sse.php?${params.toString()}`;
		this.eventSource = new EventSource(eventSourceURL);

		this.eventSource.addEventListener('progress', (event) => {
			const data = JSON.parse(event.data);
			if (data.progress !== undefined && data.progress > this.progress) {
				this.progress = Math.min(data.progress, 99);
			}
			if (data.cacheSize !== undefined) {
				this.setAttribute('cache-size', data.cacheSize);
			}
		});

		this.eventSource.addEventListener('complete', (event) => {
			const data = JSON.parse(event.data);
			if (data.progress !== undefined) {
				this.progress = Math.min(data.progress, 99);
			}
			if (data.cacheSize !== undefined) {
				this.setAttribute('cache-size', data.cacheSize);
			}
			this.stopProgressSSE();
		});

		this.eventSource.addEventListener('timeout', (event) => {
			console.warn('Progress SSE timed out');
			this.stopProgressSSE();
		});

		this.eventSource.addEventListener('error', (event) => {
			console.error('SSE error event:', event);
			this.stopProgressSSE();
		});
	}


	/**
	 * Stop the progress server-sent events (SSE)
	 * @returns {void}
	 * */
	stopProgressSSE() {
		if (this.eventSource) {
			this.eventSource.close();
			this.eventSource = null;

			// Clean up progress data on server
			fetch('ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({
					cleanupProgress: true,
					platform: this.attr('platform'),
					instance: this.attr('instance'),
					community: this.attr('community'),
					subreddit: this.attr('subreddit')
				})
			}).catch(error => {
				console.warn('Failed to clean up progress data:', error);
			});
		}
	}


	/**
	 * Clean up timer and abort controllers when the element is removed from the DOM
	 * @returns {void}
	 * */
	disconnectedCallback () {
		clearTimeout(this.debounceTimer);
		clearTimeout(this.copyTimeout);
		clearTimeout(this.progressSSETimer);
		clearTimeout(this._getSubredditsDebounce);
		clearTimeout(this.skeletonDelayTimer);
		this._getSubredditsController?.abort();
		this.stopProgressSSE();
		this._getPostsController?.abort();
	}
});
