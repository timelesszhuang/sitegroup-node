$(document).ready(function(){  
    $(".meundh li a").each(function(){  
        $this = $(this);  
        if($this[0].href==String(window.location)){  
            $this.addClass("selected");  
        }  
    });  
});
