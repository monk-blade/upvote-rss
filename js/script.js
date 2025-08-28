const { createApp, ref } = Vue

createApp({
  data() {
    return {
      loading: false,
      progress: 0,
      error: false,
      posts: [],
      platform: platform,
      defaultPlatform: defaultPlatform,
      demoMode: demoMode,
      redditEnabled: redditEnabled,
      redditDefaultDomain: redditDefaultDomain,
      redditDefaultDomainOverride: redditDefaultDomainOverride,
      redditDomain: redditDomain,
      overrideRedditDomain: overrideRedditDomain,
      subreddit: subreddit,
      subreddits: [],
      instance: instance,
      instanceHackerNewsDefault: instanceHackerNewsDefault,
      instanceLemmyDefault: instanceLemmyDefault,
      instanceMbinDefault: instanceMbinDefault,
      instancePieFedDefault: instancePieFedDefault,
      community: community,
      communityType: communityType,
      communityHackerNewsDefault: communityHackerNewsDefault,
      communityLemmyDefault: communityLemmyDefault,
      communityLobstersDefault: communityLobstersDefault,
      communityLobstersDefaultCategory: communityLobstersDefaultCategory,
      communityLobstersDefaultTag: communityLobstersDefaultTag,
      communityMbinDefault: communityMbinDefault,
      communityPieFedDefault: communityPieFedDefault,
      community_icon: null,
      community_url: null,
      community_title: null,
      community_description: null,
      community_nsfw: null,
      platform_icon: null,
      filterType: filterType || 'score',
      scoreFilterAvailablePlatforms: scoreFilterAvailablePlatforms,
      thresholdFilterAvailablePlatforms: thresholdFilterAvailablePlatforms,
      averagePostsPerDayFilterAvailablePlatforms: averagePostsPerDayFilterAvailablePlatforms,
      score: score || 1000,
      scoreDefaultHackerNews: scoreDefaultHackerNews,
      scoreDefaultLemmy: scoreDefaultLemmy,
      scoreDefaultLobsters: scoreDefaultLobsters,
      scoreDefaultMbin: scoreDefaultMbin,
      scoreDefaultPieFed: scoreDefaultPieFed,
      scoreDefaultReddit: scoreDefaultReddit,
      threshold: percentage || 100,
      averagePostsPerDay: averagePostsPerDay || 3,
      showScore: showScore || false,
      includeContent: includeContent || false,
      summaryEnabled: summaryEnabled || false,
      includeSummary: includeSummary || false,
      includeComments: includeComments || false,
      comments: comments || 0,
      pinnedCommentsFilterAvailablePlatforms: pinnedCommentsFilterAvailablePlatforms,
      filterPinnedComments: filterPinnedComments || false,
      filterNSFW: filterNSFW || false,
      blurNSFW: blurNSFW || false,
      filterOldPosts: filterOldPosts || false,
      postCutoffDays: postCutoffDays || 0,
      message: null,
      cacheSize: cacheSize,
      rssURL: '',
      copied: false,
      showRssURL: false,
      progressRadius: 80,
      progressStroke: 10,
      progressNormalizedRadius: 0,
      progressCircumference: 0,
      progressStrokeDashOffset: 0,
      darkMode: 'auto',
    }
  },
  methods: {
    debouncedSearch(timeout = 10,  event = null) {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        if (event === null || event.target.value.length > 0) {
          this.getPosts();
        }
      }, timeout);
    },
    generateButton() {
      document.querySelector('.post-list').scrollTo(0, 0);
      if(window.innerWidth < 992) {
        window.scrollTo({
          top: document.querySelector('#step-2').offsetTop - 30,
          behavior: 'smooth'
        });
      }
    },
    updateURL() {
      const newURL = new URL(window.location.href.split("?")[0]);
      newURL.searchParams.set('platform', this.platform);
      if(this.platform == 'reddit') {
        newURL.searchParams.set('subreddit', this.subreddit);
        if(this.redditDomain != this.redditDefaultDomain) {
          newURL.searchParams.set('redditDomain', this.redditDomain);
        }
      }
      if(this.platform == 'hacker-news') {
        newURL.searchParams.set('community', this.community);
      }
      if(this.platform == 'lemmy' || this.platform == 'mbin' || this.platform == 'piefed') {
        newURL.searchParams.set('instance', this.instance);
        newURL.searchParams.set('community', this.community);
      }
      if(this.platform == 'lobsters') {
        newURL.searchParams.set('community', this.community);
        newURL.searchParams.set('type', this.communityType);
      }
      if(this.filterType == 'score') {
        newURL.searchParams.set('score', this.score);
      } else if(this.filterType == 'threshold') {
        newURL.searchParams.set('threshold', this.threshold);
      } else if(this.filterType == 'averagePostsPerDay') {
        newURL.searchParams.set('averagePostsPerDay', this.averagePostsPerDay);
      }
      if(this.showScore) {
        newURL.searchParams.append('showScore', '');
      }
      if(this.includeContent) {
        newURL.searchParams.append('content', '');
      } else {
        newURL.searchParams.append('content', '0');
      }
      if(this.includeSummary) {
        newURL.searchParams.append('summary', '');
      }
      if(this.comments > 0) {
        newURL.searchParams.set('comments', this.comments);
      }
      if(this.filterPinnedComments) {
        newURL.searchParams.append('filterPinnedComments', '');
      }
      if(this.filterNSFW) {
        newURL.searchParams.append('filterNSFW', '');
      } else if(this.blurNSFW) {
        newURL.searchParams.append('blurNSFW', '');
      }
      if(this.filterOldPosts) {
        newURL.searchParams.append('filterOldPosts', this.postCutoffDays);
      }
      url = newURL.toString().replace(/=&/g, '&').replace(/=$/g, '');
      history.replaceState(null, null, url);
      this.rssURL = url + '&view=rss';
    },
    async getPosts() {
      setTimeout(() => {
        this.loading = true;
      }, 1);
      // Normalize instance URL
      if(this.instance.includes('://')) {
        this.instance = this.instance.replace(/(^\w+:|^)\/\//, '').replace(/\/$/, '');
      }
      if(this.instance.endsWith('/')) {
        this.instance = this.instance.slice(0, -1);
      }
      if(this.demoMode && this.platform == 'reddit') {
        this.platform = this.defaultPlatform;
      }
      const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          getPosts: true,
          platform: this.platform,
          redditDomain: this.redditDomain,
          subreddit: this.subreddit,
          instance: this.instance,
          community: this.community,
          communityType: this.communityType,
          filterType: this.filterType,
          score: this.score,
          threshold: this.threshold,
          averagePostsPerDay: this.averagePostsPerDay,
          includeComments: this.includeComments,
          comments: this.comments,
          filterPinnedComments: this.filterPinnedComments,
          filterNSFW: this.filterNSFW,
          blurNSFW: this.blurNSFW,
          filterOldPosts: this.filterOldPosts,
          postCutoffDays: this.postCutoffDays,
        })
      });
      const json = await response.json();
      if (json.error) {
        this.posts = [];
        this.community_icon = null;
        this.community_url = null;
        this.community_title = null;
        this.community_description = null;
        this.community_nsfw = null;
        this.error = json.error;
        this.loading = false;
        this.progress = 1;
        console.log(this.error);
        return;
      } else {
        const posts = json.filtered_posts;
        if (this.redditDefaultDomain !== this.redditDomain) {
          for (let i = 0; i < posts.length; i++) {
            posts[i].permalink = posts[i].permalink.replace(this.redditDefaultDomain, this.redditDomain);
          }
        }
        this.posts = posts;
        this.error = false;
        this.community = json.community_slug;
        this.instance = json.community_instance;
        this.community_icon = json.community_icon;
        this.community_url = json.community_url;
        this.community_title = json.community_title;
        this.community_description = json.community_description;
        this.community_nsfw = json.community_nsfw;
        this.platform_icon = json.platform_icon;
        this.message = json.message;
        this.cacheSize = json.cacheSize;
        this.loading = false;
        this.progress = 1;
        this.updateURL();
        document.querySelector('.post-list').scrollTo(0, 0);
      }
    },
    selectSubreddit(subreddit) {
      this.subreddit = subreddit;
      this.debouncedSearch();
    },
    async getSubreddits() {
      if(this.subreddit.length < 3) {
        return;
      }
      const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          getSubreddits: true,
          subreddit: this.subreddit,
        })
      });
      const json = await response.json();
      if (json.error) {
        this.error = json.error;
        console.log(this.error);
        return;
      } else {
        for (let i = 0; i < json.subreddits.length; i++) {
          this.subreddits.push(json.subreddits[i]['name']);
        }
        this.subreddits = [...new Set(this.subreddits)].sort();
      }
    },
    async clearCache() {
      document.querySelector('.post-list').scrollTo(0, 0);
      if(window.innerWidth < 992) {
        window.scrollTo({
          top: document.querySelector('#step-1').offsetTop - 30,
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
        this.cacheSize = json.cacheSize;
        this.posts = [];
        this.getPosts();
      }
    },
    filterInformation() {
      document.querySelector('.filter-information').classList.toggle('open');
    },
    fiddlyBits() {
      document.querySelector('fieldset.fiddly-bits').classList.toggle('open');
    },
    copyRSSurl(event) {
      const url = new URL(window.location.href);
      url.searchParams.delete('view');
      url.searchParams.set('view', 'rss');
      navigator.clipboard.writeText(url.href);
      this.copied = true;
      let button = event.target.closest('button');
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
      setTimeout(() => {
        span.classList.add('animate');
      }, 1);
      setTimeout(() => {
        span.remove();
      }, 800);
      clearTimeout(this.copyTimeout);
      this.copyTimeout = setTimeout(() => {
        this.copied = false;
      }, 1500);
    },
  },
  computed: {
    scoreFilterAvailable() {
      return this.scoreFilterAvailablePlatforms.includes(this.platform);
    },
    thresholdFilterAvailable() {
      return this.thresholdFilterAvailablePlatforms.includes(this.platform);
    },
    averagePostsPerDayFilterAvailable() {
      return this.averagePostsPerDayFilterAvailablePlatforms.includes(this.platform);
    },
    pinnedCommentsFilterAvailable() {
      return this.pinnedCommentsFilterAvailablePlatforms.includes(this.platform);
    },
  },
  created() {
    this.getPosts();
    this.progressNormalizedRadius = this.progressRadius - this.progressStroke * 2;
    this.progressCircumference = this.progressNormalizedRadius * 2 * Math.PI;
    this.darkMode = sessionStorage.getItem('darkMode') || 'auto';
    this.showRssURL = navigator.clipboard ? false : true;
    setTimeout(() => {
      document.querySelector('#app').classList.add('initialized');
    }, 1200);
  },
  watch: {
    loading() {
      if(this.loading) {
        this.progress = 1;
        this.interval = setInterval(() => {
          fetch('ajax.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              getProgress: true,
              platform: this.platform,
              instance: this.instance,
              community: this.community,
              subreddit: this.subreddit
            })
          })
          .then(response => response.json())
          .then(json => {
            if(json.progress == 100) {
              clearInterval(this.interval);
            }
            if (json.progress > this.progress) {
              this.progress = json.progress;
            }
          })
          .catch((error) => {
            console.error('Error:', error);
          }
          );
        }, 500);
      } else {
        clearInterval(this.interval);
        setTimeout(() => {
          this.progress = 1;
        }, 1000);
      }
    },
    platform() {
      if(this.platform == 'hacker-news') {
        this.instance = this.instanceHackerNewsDefault;
        this.community = this.communityHackerNewsDefault;
        this.filterType = 'averagePostsPerDay';
        this.score = this.scoreDefaultHackerNews;
        if(this.filterType == 'threshold') {
          this.filterType = 'score';
        }
      }
      if(this.platform == 'lemmy') {
        this.instance = this.instanceLemmyDefault;
        this.community = this.communityLemmyDefault;
        this.score = this.scoreDefaultLemmy;
        setTimeout(function(){
          document.getElementById('community').focus();
        }, 1);
      }
      if(this.platform == 'lobsters') {
        this.community = this.communityLobstersDefault;
        this.communityType = this.communityLobstersDefault;
        this.filterType = 'score';
        this.score = this.scoreDefaultLobsters;
      }
      if(this.platform == 'mbin') {
        this.instance = this.instanceMbinDefault;
        this.community = this.communityMbinDefault;
        this.score = this.scoreDefaultMbin;
        setTimeout(function(){
          document.getElementById('community').focus();
        }, 1);
      }
      if(this.platform == 'piefed') {
        this.instance = this.instancePieFedDefault;
        this.community = this.communityPieFedDefault;
        this.score = this.scoreDefaultPieFed;
        setTimeout(function(){
          document.getElementById('community').focus();
        }, 1);
      }
      if(this.platform == 'reddit') {
        this.score = this.scoreDefaultReddit;
        setTimeout(function(){
          document.getElementById('subreddit').focus();
        }, 1);
      }
      this.debouncedSearch();
    },
    communityType() {
      if(this.platform == 'lobsters' && this.communityType == 'all') {
        this.community = this.communityLobstersDefault;
        this.debouncedSearch();
      }
      if(this.platform == 'lobsters' && this.communityType == 'category') {
        this.community = this.communityLobstersDefaultCategory;
        setTimeout(function(){
          document.getElementById('category').focus();
        }, 1);
        this.debouncedSearch();
      }
      if(this.platform == 'lobsters' && this.communityType == 'tag') {
        this.community = this.communityLobstersDefaultTag;
        setTimeout(function(){
          document.getElementById('tag').focus();
        }, 1);
        this.debouncedSearch();
      }
    },
    overrideRedditDomain() {
      this.redditDomain = this.overrideRedditDomain ? this.redditDefaultDomainOverride : this.redditDefaultDomain;
      if(this.overrideRedditDomain) {
        setTimeout(function(){
          document.getElementById('reddit-domain').focus();
        }, 1);
      }
      this.debouncedSearch();
    },
    subreddit() {
      this.getSubreddits();
    },
    filterType() {
      this.debouncedSearch();
    },
    showScore() {
      this.updateURL();
    },
    includeContent() {
      this.updateURL();
    },
    includeSummary() {
      this.updateURL();
    },
    includeComments() {
      if(this.includeComments) {
        this.comments = 5;
      } else {
        this.comments = 0;
      }
      this.updateURL();
    },
    comments() {
      this.updateURL();
    },
    filterPinnedComments() {
      this.updateURL();
    },
    filterNSFW() {
      this.debouncedSearch();
    },
    blurNSFW() {
      this.debouncedSearch();
    },
    filterOldPosts() {
      if(this.postCutoffDays == 0) {
        this.postCutoffDays = 7;
      }
      this.debouncedSearch();
    },
    progress() {
      this.progressStrokeDashOffset = this.progressCircumference - this.progress / 100 * this.progressCircumference;
    },
    darkMode() {
      document.documentElement.dataset.colorScheme = this.darkMode;
      sessionStorage.setItem('darkMode', this.darkMode);
    },
  }
}).mount('#app');