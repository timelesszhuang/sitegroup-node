## 压缩之后模板位置

**简单 静态化相关说明**


1、模板文件上传之后存储在 upload/ziptemplate 中。   

2、随后解压缩模板文件  然后复制 /static 下的 img、css、js等数据
到 static 目录下。   

3、嵌套好 **替换标签** 的 html 页面被复制到  template 中，每个.html为站点的模板页。   

4、thinkphp 配置 view 的位置为 public/template 下 。

5、生成的静态页 放在 /public 根目录中 其他页面在 相对应的目录中。



* html 模板文件存储位置
* 其他的css 之类存储在上一级 static 中
