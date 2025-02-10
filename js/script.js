const { createApp, ref } = Vue

createApp({
  data() {
    return {
      loading: false,
      progress: 1,
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
      community: community,
      communityHackerNewsDefault: communityHackerNewsDefault,
      communityLemmyDefault: communityLemmyDefault,
      communityMbinDefault: communityMbinDefault,
      community_icon: null,
      community_url: null,
      community_title: null,
      community_description: null,
      community_nsfw: null,
      platform_icon: null,
      filterType: filterType || 'score',
      scoreFilterAvailable: scoreFilterAvailable,
      thresholdFilterAvailable: thresholdFilterAvailable,
      averagePostsPerDayFilterAvailable: averagePostsPerDayFilterAvailable,
      score: score || 1000,
      threshold: percentage || 100,
      averagePostsPerDay: averagePostsPerDay || 3,
      showScore: showScore || false,
      includeContent: includeContent || false,
      summaryEnabled: summaryEnabled || false,
      includeSummary: includeSummary || false,
      includeComments: includeComments || false,
      comments: comments || 0,
      filterNSFW: filterNSFW || false,
      blurNSFW: blurNSFW || false,
      filterOldPosts: filterOldPosts || false,
      postCutoffDays: postCutoffDays || 0,
      message: null,
      cacheSize: cacheSize,
      copied: false,
      progressRadius: 80,
      progressStroke: 10,
      progressNormalizedRadius: 0,
      progressCircumference: 0,
      progressStrokeDashOffset: 0,
      darkMode: 'auto',
    }
  },
  methods: {
    submitForm() {
      this.getPosts();
    },
    debouncedSearch(event) {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        if(event.target.value.length > 0) {
          this.getPosts();
        }
      }, 500);
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
      if(this.platform == 'lemmy' || this.platform == 'mbin') {
        newURL.searchParams.set('instance', this.instance);
        newURL.searchParams.set('community', this.community);
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
          filterType: this.filterType,
          score: this.score,
          threshold: this.threshold,
          averagePostsPerDay: this.averagePostsPerDay,
          includeComments: this.includeComments,
          comments: this.comments,
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
        this.progressStrokeDashOffset = this.progressCircumference - this.progress / 100 * this.progressCircumference;
        this.updateURL();
        document.querySelector('.post-list').scrollTo(0, 0);
      }
    },
    selectSubreddit(subreddit) {
      this.subreddit = subreddit;
      this.getPosts();
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
  created() {
    this.getPosts();
    this.progressNormalizedRadius = this.progressRadius - this.progressStroke * 2;
    this.progressCircumference = this.progressNormalizedRadius * 2 * Math.PI;
    this.darkMode = sessionStorage.getItem('darkMode') || 'auto';
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
      this.scoreFilterAvailable = true;
      this.thresholdFilterAvailable = true;
      this.averagePostsPerDayFilterAvailable = true;
      if(this.platform == 'hacker-news') {
        this.instance = this.instanceHackerNewsDefault;
        this.community = this.communityHackerNewsDefault;
        this.thresholdFilterAvailable = false;
        if(this.filterType == 'threshold') {
          this.filterType = 'score';
        }
      }
      if(this.platform == 'lemmy') {
        this.instance = this.instanceLemmyDefault;
        this.community = this.communityLemmyDefault;
      }
      if(this.platform == 'mbin') {
        this.instance = this.instanceMbinDefault;
        this.community = this.communityMbinDefault;
      }
      this.getPosts();
      if(this.platform == 'reddit') {
        setTimeout(function(){
          document.getElementById('subreddit').focus();
        }, 1);
      }
    },
    overrideRedditDomain() {
      this.redditDomain = this.overrideRedditDomain ? this.redditDefaultDomainOverride : this.redditDefaultDomain;
      if(this.overrideRedditDomain) {
        setTimeout(function(){
          document.getElementById('reddit-domain').focus();
        }, 1);
      }
      this.getPosts();
    },
    subreddit() {
      this.getSubreddits();
    },
    filterType() {
      this.getPosts();
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
    filterNSFW() {
      this.getPosts();
    },
    blurNSFW() {
      this.getPosts();
    },
    filterOldPosts() {
      if(this.postCutoffDays == 0) {
        this.postCutoffDays = 7;
      }
      this.getPosts();
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