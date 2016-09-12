var score_manager = {
	user_id : 0,
	game_id : '',
	url: '/wp-content/plugins/scorecharts/ajax/',
	init: function(game_id) {
		this.game_id = game_id;
		this.get_userid();
		this.submit
	},
	get_userid: function() {
		var _this = this;
		$.ajax({
			url: this.url + 'get-user.php',
			success: function(data) {
				_this.user_id = JSON.parse(data).user_id;
				localStorage.setItem('user_id', _this.user_id);
			},
			error: function() {
				var id = localStorage.getItem('user_id');
				if(id != null && !isNaN(id))
					_this.user_id = id;
			}
		});
	},
	submit: function(data) {
		var _this = this;
		var scores = null;
		try {
			scores = JSON.parse(localStorage.getItem('scores'));
		} catch(e) {
		}
		if(scores == null || typeof scores != 'object')
			scores = [];
		if(data != null) {
			data.user_id = this.user_id;
			data.game_id = this.game_id;
			data.game_date = Math.floor(new Date().getTime() / 1000);
			scores.push(data);
			localStorage.setItem('scores', JSON.stringify(scores));
		}
		if(scores.length > 0) {
			$.ajax({
				url: _this.url + 'submit-scores.php',
				type: 'POST',
				data: { scores : scores },
				success: function() {
					localStorage.setItem('scores', JSON.stringify([]));
				},
				error: function() {
				}
			});
		}
	}
}

score_manager.submit(null);