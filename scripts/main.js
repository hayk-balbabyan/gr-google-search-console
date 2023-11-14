jQuery(document).ready(function($) {
    $('.page_pagination_items').on('click', function(){
        $('.page_pagination_items').removeClass('active');
        $(this).addClass('active');
    })
    
    let more_pages = [];
    $('.get_pages_button').on('click', function(){
        let button = $(this);
        button.addClass('loading')
        let site_url =  $('#site_url').val();
        let keyword = $('.search_keyword').val()
        if(keyword == ''){
            alert('Please enter text');
            button.removeClass('loading')
            return
        }
        $('.pages').empty();
  
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_pages_by_site_url',
                site_url: site_url,
                keyword:keyword,
                // page: page,
            },
            success: function(response) {
                button.removeClass('loading')
                
                // console.log(response);
                if(response.pages){
                    $('.load_more_block').removeClass('dnone')
                    let table = '<table style="margin-top:30px;" class="wp-list-table widefat fixed striped table-view-list"><thead><tr><th class="manage-column">Url</th><th class="manage-column">Search</th><th class="manage-column">Clicks</th><th class="manage-column">Impressions</th></tr></thead><tbody id="the-list"></tbody></table>';
                    $('.pages').append(table)
                    for (const url in response.pages) {
                        let pageUrl = response.pages[url].pageUrl;
                        let queries = response.pages[url].queries;
                        let show_url = true;
                        if(queries){
                            $('#the-list').append(createTable(queries,pageUrl,show_url))
                        }
                    }
                }
                
                if(response.more_pages){
                    more_pages = response.more_pages;
                }
            }
        });
    })
    
    let more_pages_count = 30;
    $('.load_more_button').on('click', function(){
        let button = $(this);
        button.addClass('loading')
        let site_url =  $('#site_url').val();
        let keyword = $('.search_keyword').val()
        
         if(keyword == ''){
            alert('Please enter text');
            button.removeClass('loading')
            return
        }

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_more_pages',
                site_url: site_url,
                keyword:keyword,
                more_pages: more_pages,
                more_pages_count:more_pages_count
            },
            success: function(response) {
                button.removeClass('loading')
                // console.log(response);
                if(response.pages.length > 0){

                    for (const url in response.pages) {
                        let pageUrl = response.pages[url].pageUrl;
                        let queries = response.pages[url].queries;
                        let show_url = true;
                        if(queries){
                            $('#the-list').append(createTable(queries,pageUrl,show_url))
                        }
                    }
                }else{
                    button.hide();
                    $('.load_more_block').append('<p style="text-align:center;">No more pages</p>')
                }
                
                if(response.more_pages){
                    more_pages = response.more_pages;
                }
                
                if(response.more_pages_count){
                    more_pages_count = more_pages_count;
                }
            }
        });
        
    })
    
    function createTable(data,pageUrl,show_url) {
    //   let table = '<table style="margin-top:30px;" class="wp-list-table widefat fixed striped table-view-list"><thead><tr><th class="manage-column">Url</th><th class="manage-column">Search</th><th class="manage-column">Clicks</th><th class="manage-column">Impressions</th></tr></thead><tbody id="the-list">';
       let table = '';
      data.forEach(item => {
          
        table += '<tr>';
        if(show_url){
            show_url = false;
        }else{
             pageUrl = '';
        }
        table += `<td>${pageUrl}</td>`;
        
        table += `<td>${item.keys[0]}</td>`;
        table += `<td>${item.clicks}</td>`;
        table += `<td>${item.impressions}</td>`;
        table += '</tr>';
      });
      
  
      return table;
    }

    $('#site_url').on('change', function(){
        $('.search_block').addClass('dnone')
        if($(this).val() != '' ){
            $('.search_block').removeClass('dnone')
        }
    })
    
    
    
    
});