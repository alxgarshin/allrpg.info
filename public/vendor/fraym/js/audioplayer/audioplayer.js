/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class FraymAudioPlayer {
	constructor(element, options) {
		if (!element) return false;
		if (element.tagName.toLowerCase() != 'audio' || _(element).hasClass('fraymAudioPlayerApplied')) return false;

		this.element = _(element);

		this.element.addClass('fraymAudioPlayerApplied');

		this.options = Object.assign({
			classPrefix: 'audioplayer',
			strPlay: 'Play',
			strPause: 'Pause',
			strVolume: 'Volume'
		}, options);

		const audioFile = this.element.attr('src');

		const cssClass = {};

		const cssClassSub = {
			playPause: 'playpause',
			playing: 'playing',
			stopped: 'stopped',
			time: 'time',
			timeCurrent: 'time-current',
			timeDuration: 'time-duration',
			bar: 'bar',
			barLoaded: 'bar-loaded',
			barPlayed: 'bar-played',
			volume: 'volume',
			volumeButton: 'volume-button',
			volumeAdjust: 'volume-adjust',
			noVolume: 'novolume',
			muted: 'muted',
			mini: 'mini'
		};

		for (let subName in cssClassSub) {
			cssClass[subName] = this.options.classPrefix + '-' + cssClassSub[subName];
		}

		const
			isTouch = touchingDevice,
			eStart = isTouch ? 'touchstart' : 'mousedown',
			eMove = isTouch ? 'touchmove' : 'mousemove',
			eEnd = isTouch ? 'touchend' : 'mouseup',
			eCancel = isTouch ? 'touchcancel' : 'mouseup';

		function secondsToTime(secs) {
			const
				hoursDiv = secs / 3600,
				hours = Math.floor(hoursDiv),
				minutesDiv = secs % 3600 / 60,
				minutes = Math.floor(minutesDiv),
				seconds = Math.ceil(secs % 3600 % 60);

			if (seconds > 59) { seconds = 0; minutes = Math.ceil(minutesDiv); }
			if (minutes > 59) { minutes = 0; hours = Math.ceil(hoursDiv); }

			return (hours == 0 ? '' : hours > 0 && hours.toString().length < 2 ? '0' + hours + ':' : hours + ':') + (minutes.toString().length < 2 ? '0' + minutes : minutes) + ':' + (seconds.toString().length < 2 ? '0' + seconds : seconds);
		};

		function canPlayType(file) {
			const audioElement = document.createElement('audio');

			return !!(audioElement.canPlayType && audioElement.canPlayType('audio/' + file.split('.').pop().toLowerCase() + ';').replace(/no/, ''));
		};

		const
			isAutoPlay = this.element.attr('autoplay') === '' || this.element.attr('autoplay') === 'autoplay',
			isLoop = this.element.attr('loop') === '' || this.element.attr('loop') === 'loop';

		const supportedType = canPlayType(audioFile);

		const player = _(elFromHTML(`<div class="${this.options.classPrefix}">` + (supportedType ? `<div>${element.cloneNode(true).outerHTML}</div>` : `<embed src="${audioFile}" width="0" height="0" volume="100" autostart="${isAutoPlay.toString()}" loop="${isLoop.toString()}" />`) + `<div class="${cssClass.playPause}" title="${this.options.strPlay}"><a>${this.options.strPlay}</a></div></div>`));

		const theAudio = player.find(supportedType ? 'audio' : 'embed').asDomElement();

		if (supportedType) {
			theAudio.style.width = 0;
			theAudio.style.height = 0;
			theAudio.style.visibility = 'hidden';

			player.insert(`<div class="${cssClass.time} ${cssClass.timeCurrent}"></div><div class="${cssClass.bar}"><div class="${cssClass.barLoaded}"></div><div class="${cssClass.barPlayed}"></div></div><div class="${cssClass.time} ${cssClass.timeDuration}"></div><div class="${cssClass.volume}"><div class="${cssClass.volumeButton}" title="${this.options.strVolume}"><a>${this.options.strVolume}</a></div><div class="${cssClass.volumeAdjust}"><div><div></div></div></div></div>`, 'end');

			const
				theBar = player.find('.' + cssClass.bar).first(),
				barPlayed = player.find('.' + cssClass.barPlayed).first(),
				barLoaded = player.find('.' + cssClass.barLoaded).first(),
				timeCurrent = player.find('.' + cssClass.timeCurrent).first(),
				timeDuration = player.find('.' + cssClass.timeDuration).first(),
				volumeButton = player.find('.' + cssClass.volumeButton).first(),
				volumeAdjuster = player.find(`.${cssClass.volumeAdjust} > div`).first();

			let volumeDefault = 0;

			function updateLoadBar() {
				const interval = setInterval(function () {
					if (theAudio.buffered.length < 1) {
						return true;
					}

					barLoaded.asDomElement().style.width = (theAudio.buffered.end(0) / theAudio.duration) * 100 + '%';

					if (Math.floor(theAudio.buffered.end(0)) >= Math.floor(theAudio.duration)) {
						clearInterval(interval);
					}
				}, 100);
			};

			const
				volumeTestDefault = theAudio.volume,
				volumeTestValue = theAudio.volume = 0.111;

			if (Math.round(theAudio.volume * 1000) / 1000 == volumeTestValue) {
				theAudio.volume = volumeTestDefault;
			}
			else {
				player.addClass(cssClass.noVolume);
			}

			timeDuration.html('&hellip;');
			timeCurrent.html(secondsToTime(0));

			theAudio.addEventListener('loadeddata', function () {
				updateLoadBar();
				timeDuration.html(isNumeric(theAudio.duration) ? secondsToTime(theAudio.duration) : '&hellip;');
				volumeAdjuster.find('div').asDomElement().style.height = theAudio.volume * 100 + '%';
				volumeDefault = theAudio.volume;
			});

			theAudio.addEventListener('timeupdate', function () {
				timeCurrent.html(secondsToTime(theAudio.currentTime));
				barPlayed.asDomElement().style.width = (theAudio.currentTime / theAudio.duration) * 100 + '%';
			});

			theAudio.addEventListener('volumechange', function () {
				volumeAdjuster.find('div').asDomElement().style.height = theAudio.volume * 100 + '%';
				if (theAudio.volume > 0 && player.hasClass(cssClass.muted)) player.removeClass(cssClass.muted);
				if (theAudio.volume <= 0 && !player.hasClass(cssClass.muted)) player.addClass(cssClass.muted);
			});

			theAudio.addEventListener('ended', function () {
				player.removeClass(cssClass.playing);
				player.addClass(cssClass.stopped);
			});

			function adjustCurrentTime(e) {
				const theRealEvent = isTouch ? e.touches[0] : e;
				const offset = theBar.offset();
				const width = theBar.asDomElement().getBoundingClientRect().width;

				theAudio.currentTime = Math.round((theAudio.duration * (theRealEvent.pageX - offset.left)) / width);
			};

			function adjustCurrentTimeWrapper(e) {
				adjustCurrentTime(e);
			}

			theBar
				.on(eStart, function (e) {
					e.stopImmediatePropagation();

					adjustCurrentTime(e);
					theBar.on(eMove, adjustCurrentTimeWrapper);
				})
				.on(eCancel + ' ' + eEnd, function (e) {
					e.stopImmediatePropagation();

					theBar.removeEventListener(eMove, adjustCurrentTimeWrapper);
				});

			volumeButton.on('click', function () {
				if (player.hasClass(cssClass.muted)) {
					player.removeClass(cssClass.muted);
					theAudio.volume = volumeDefault;
				}
				else {
					player.addClass(cssClass.muted);
					volumeDefault = theAudio.volume;
					theAudio.volume = 0;
				}

				return false;
			});

			function adjustVolume(e) {
				const theRealEvent = isTouch ? e.touches[0] : e;
				const offset = volumeAdjuster.offset();
				const height = volumeAdjuster.asDomElement().getBoundingClientRect().height;
				const volume = Math.abs((theRealEvent.pageY - (offset.top + height)) / height);

				theAudio.volume = volume > 1 ? 1 : volume < 0 ? 0 : volume;
			};

			function adjustVolumeWrapper(e) {
				adjustVolume(e);
			}

			volumeAdjuster
				.on(eStart, function (e) {
					e.stopImmediatePropagation();

					adjustVolume(e);
					volumeAdjuster.on(eMove, adjustVolumeWrapper);
				})
				.on(eCancel + ' ' + eEnd, function (e) {
					e.stopImmediatePropagation();

					volumeAdjuster.removeEventListener(eMove, adjustVolumeWrapper);
				});
		}
		else {
			player.addClass(cssClass.mini);
		}

		player.addClass(isAutoPlay ? cssClass.playing : cssClass.stopped);

		player.find(`.${cssClass.playPause}`).first().on('click', function (e) {
			e.stopImmediatePropagation();

			const self = _(this);

			if (player.hasClass(cssClass.playing)) {
				self.attr('title', options.strPlay);
				self.find('a').asDomElement().innerHTML = options.strPlay;
				player.removeClass(cssClass.playing);
				player.addClass(cssClass.stopped);
				supportedType ? theAudio.pause() : theAudio.Stop();
			} else {
				self.attr('title', options.strPause);
				self.find('a').asDomElement().innerHTML = options.strPause;
				player.addClass(cssClass.playing);
				player.removeClass(cssClass.stopped);
				supportedType ? theAudio.play() : theAudio.Play();
			}
		});

		element.replaceWith(player.asDomElement());
	}
}