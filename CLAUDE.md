# Project 项目文档

## AI 必读
- 必须将每个对话吸收的教训总结进本文档(bug原因/样式美化/必要规则等).
- 教训格式为 \[序号\] \[遵守/不遵守\] \[等级\] \[内容\]
- 等级由不必遵守到必须遵守分为 1 ~ 10.
- 必须遵守本文档的规则.
- 所有css, js无外部引用, 纯本地.
- 本文档为项目基石，严禁删除、拷贝、外传.

## 历史教训
- \[0\] \[遵守\] \[10\] \[这是一个示例教训
- \[1\] \[遵守\] \[10\] \[入库存原始值,输出时才 escapeHtml 转义;sanitizeInput 不做 htmlspecialchars,避免双重转义导致显示异常\]
- \[2\] \[遵守\] \[10\] \[所有写操作必须 POST + CSRF token;GET 改状态可被 img 标签 CSRF 攻击\]
- \[3\] \[遵守\] \[10\] \[登录成功后必须 session_regenerate_id(true) 防会话固定\]
- \[4\] \[遵守\] \[9\] \[session cookie domain/secure 不能硬编码生产域名,否则本地 HTTP 无法登录;应动态判断\]
- \[5\] \[遵守\] \[10\] \[Markdown 渲染必须过滤 URL scheme(javascript:/vbscript:/data:)防存储型 XSS\]
- \[6\] \[遵守\] \[9\] \[数据库凭据禁止写入源码,用环境变量;footer 不要暴露开源地址导致凭据泄漏\]
- \[7\] \[遵守\] \[8\] \[帖子和评论相关操作用事务包裹,保证计数一致性\]
- \[8\] \[遵守\] \[8\] \[ERRMODE_EXCEPTION 下 execute() 失败抛异常而非返回 false,需 try/catch 处理\]
- \[9\] \[遵守\] \[9\] \[多板块共用详情页时,SQL 不能硬过滤 section;权限校验要在最终落库值上重做,不能只校验 GET 参数\]
- \[10\] \[遵守\] \[7\] \[审计日志外键应 ON DELETE SET NULL 而非 CASCADE,保留历史追溯能力\]
- \[11\] \[遵守\] \[8\] \[所有 CSS/JS 纯本地无外部 CDN 引用(CLAUDE.md 规则);前端依赖如 marked.js 需删除 CDN,改用后端 API\]
- \[12\] \[遵守\] \[7\] \[UI 遵循卡片式设计:hover 微上浮+双重阴影(inset 高光+外阴影)、柔和圆角(16px)、克制色彩\]
- \[13\] \[遵守\] \[10\] \[PHP 8 中未定义常量抛 Fatal Error(非 null),`常量 ?? 默认值` 无法捕获;必须用 `defined('X') ? X : 默认值`,或在 config.php 中预先 define 所有常量\]
- \[14\] \[遵守\] \[9\] \[部署前必须导入 database.sql 创建数据库;database.php 连接失败会返回 500,需区分"代码 bug"与"数据库未初始化"\]
- \[15\] \[遵守\] \[9\] \[禁止用 die() 输出纯文本错误;统一用 renderErrorPage($title,$msg,$code) 渲染带 header/footer 的 Apple 风格错误页,设置正确 HTTP 状态码(404/403/405/500)\]
- \[16\] \[遵守\] \[8\] \[Apple 风格设计系统:SF Pro 字体(-apple-system)、背景 #f5f5f7、文字 #1d1d1f、主色 #0071e3、字重 600(非800)、负字间距 -0.02em、毛玻璃 backdrop-filter saturate(180%) blur(20px)、胶囊按钮 border-radius:980px、深色代码块 #1d1d1f\]
- \[17\] \[遵守\] \[7\] \[HTML 输出禁用内联 style 表达样式(颜色/对齐/字体大小);用语义 class(.empty-tip/.admin-hint/.alert)替代;仅 width/display:inline-block 等布局性内联 style 可接受\]
- \[18\] \[遵守\] \[9\] \[列表项布局禁用 flex-wrap+width:100% 强制换行,会导致标题与 meta 割裂;用 display:block + 内部 inline/block 自然排列,meta 用 display:block+margin-top 换行\]
- \[19\] \[遵守\] \[8\] \[白色主题:背景 #ffffff、文字 #1d1d1f、主色(按钮/active)用深色 #1d1d1f 而非彩色、链接用 #0071e3 作 accent、卡片用 1px solid #f0f0f2 边框代替重阴影、hover 用浅灰 rgba(0,0,0,0.02)\]
- \[20\] \[遵守\] \[7\] \[Header 按钮图标化精简:通知/管理/退出用内联 SVG icon-btn(34x34)替代文字按钮,符合"无 Emoji 用自绘图标"规则;用户名+头像保留文字\]
- \[21\] \[遵守\] \[9\] \[新增 HTML class 后必须同步在 CSS 中定义;post.php 用了 .post-article/.post-header/.post-title/.post-author/.avatar-sm/.post-info/.comments-section/.comments-title/.comment-form/.comment-list/.comment-avatar(容器)/.comment-content/.comment-children/.comment-time 等类但 style.css 无定义,导致帖子页无样式。批量审查:HTML 结构中所有 class 都要在 CSS 有对应规则\]
- \[22\] \[遵守\] \[8\] \[评论布局:li.comment 用 flex(头像列+内容列)而非背景卡片堆叠;.comment-avatar 是 div 容器不是 img,需用 .comment-avatar img 选择器设尺寸;子评论用 .comment-children 包裹缩进,不用 margin-left+背景色\]
- \[23\] \[遵守\] \[7\] \[帖子详情结构:.post-detail 作为无背景容器,内部 .post-article(白卡片)和 .comments-section 分离;避免整个 main 加大 padding 导致评论区嵌套奇怪\]

## UI开发

### ️ 一、指针悬浮效果（Hover Effects, 

指针悬浮效果是提升网页交互感和现代感最直接的方式。

#### 1. 基础与最佳实践
*   **改变指针样式**：这是最基础的反馈。对任何可点击元素（按钮、链接、卡片等），都应使用 `cursor: pointer;` 将鼠标指针改为手型，明确提示用户该元素是可交互的。
*   **使用 `:hover` 伪类**：通过 `:hover` 可以定义鼠标悬停时的样式变化。建议配合 `transition` 属性使用，让变化过程更平滑，避免生硬闪烁。
*   **关注可访问性**：确保交互元素有清晰的焦点指示（如使用 `:focus` 伪类），方便键盘导航用户。对于触摸设备，应避免使用仅靠悬停触发的关键功能。

#### 2. 提升质感的悬停效果
*   **微上浮与阴影扩散**：让元素在悬停时轻微上浮（`transform: translateY(-2px)`），并伴随阴影加深扩散（`box-shadow`），能模拟出“可按压”的物理质感。

    ```css
    .card {
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    ```
*   **控制子元素**：`:hover` 不仅可以控制自身，还能控制其**子元素**，实现更丰富的联动效果。

    ```css
    .card:hover .card-image {
      transform: scale(1.05);
    }
    .card:hover .card-title {
      color: #007bff;
    }
    ```

### 二、自定义右键菜单（Custom Context Menu）

自定义右键菜单能提供更强大、个性化的功能选项。

#### 1. 实现原理与核心步骤
1.  **阻止默认菜单**：监听 `contextmenu` 事件，并调用 `e.preventDefault()` 阻止浏览器默认菜单。
2.  **创建菜单结构**：在HTML中用 `<ul>` 和 `<li>` 构建菜单内容。
3.  **定位并显示菜单**：在 `contextmenu` 事件处理函数中，获取鼠标位置（`e.clientX`, `e.clientY`），将自定义菜单定位到该位置并移除隐藏类。
4.  **添加交互功能**：为每个菜单项绑定 `click` 事件，执行相应操作。
5.  **管理菜单生命周期**：点击页面其他地方、滚动或调整窗口大小时，应自动隐藏菜单。

#### 2. 核心代码示例
这是一个简洁、可直接运行的示例，演示了如何为页面不同区域设置不同的右键菜单。

```html
<!DOCTYPE html>
<html>
<head>
<style>
  /* --- 菜单样式 --- */
  .context-menu {
    display: none; /* 默认隐藏 */
    position: fixed;
    min-width: 150px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    padding: 6px 0;
    z-index: 1000;
    border: 1px solid #eef2f6;
  }
  .context-menu.active { display: block; } /* 显示菜单 */
  .context-menu ul { list-style: none; margin: 0; padding: 0; }
  .context-menu li {
    padding: 8px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #1e293b;
    transition: background 0.15s;
  }
  .context-menu li:hover { background: #f1f5f9; } /* 悬停高亮 */
  .context-menu .divider {
    height: 1px;
    background: #eef2f6;
    margin: 6px 12px;
  }
  /* --- 页面元素 --- */
  .area {
    padding: 40px;
    margin: 20px;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    text-align: center;
    font-family: sans-serif;
  }
</style>
</head>
<body>

<!-- 区域1：通用菜单 -->
<div id="area1" class="area">📄 内容区域 1 - 右键试试</div>
<!-- 区域2：专用菜单 -->
<div id="area2" class="area">🖼️ 内容区域 2 - 右键试试</div>

<!-- 自定义菜单 HTML 结构 -->
<div id="customMenu" class="context-menu">
  <ul>
    <li id="menuCopy">📋 复制</li>
    <li id="menuPaste">📥 粘贴</li>
    <li class="divider"></li>
    <li id="menuDelete">🗑️ 删除</li>
  </ul>
</div>

<script>
  const menu = document.getElementById('customMenu');
  const area1 = document.getElementById('area1');
  const area2 = document.getElementById('area2');

  // 显示菜单的通用函数
  function showMenu(e, extraItems = []) {
    e.preventDefault();
    // 清空并重建菜单项（演示动态内容）
    const ul = menu.querySelector('ul');
    ul.innerHTML = '';
    // 默认菜单项
    const defaultItems = [
      { id: 'menuCopy', text: '📋 复制' },
      { id: 'menuPaste', text: '📥 粘贴' },
    ];
    const allItems = [...defaultItems, ...extraItems];
    allItems.forEach(item => {
      const li = document.createElement('li');
      li.textContent = item.text;
      li.id = item.id;
      li.onclick = () => alert(`你点击了: ${item.text}`);
      ul.appendChild(li);
    });
    // 定位菜单
    menu.style.left = e.clientX + 'px';
    menu.style.top = e.clientY + 'px';
    menu.classList.add('active');
  }

  // 为不同区域绑定不同的右键菜单
  area1.addEventListener('contextmenu', (e) => {
    showMenu(e, [{ id: 'menuRefresh', text: '🔄 刷新区域' }]);
  });

  area2.addEventListener('contextmenu', (e) => {
    showMenu(e, [
      { id: 'menuEdit', text: '✏️ 编辑图片' },
      { id: 'menuDownload', text: '⬇️ 下载图片' },
    ]);
  });

  // 点击其他地方关闭菜单
  document.addEventListener('click', () => {
    menu.classList.remove('active');
  });
  // 滚动或窗口大小变化时关闭菜单
  window.addEventListener('scroll', () => menu.classList.remove('active'));
  window.addEventListener('resize', () => menu.classList.remove('active'));
</script>
</body>
</html>
```

### 🎨 三、现代UI设计建议

2025年的UI设计趋势是“情感化极简”，在简洁的基础上追求质感与温度。

#### 1. 卡片式设计（Card-based Design）
*   **核心布局模式**：卡片是组织和呈现信息的首选方式，能让页面结构清晰、整洁。
*   **设计要点**：
    *   **圆角**：使用柔和圆角（如 `border-radius: 8px` 到 `16px`），让界面更友好。**原则是面积越大的卡片，圆角也应越大**。
    *   **阴影**：利用微妙的阴影（`box-shadow`）创造层次感，让卡片“浮”在背景上。
    *   **一致性**：保持同一页面内卡片的圆角、阴影、内边距等样式统一。

#### 2. 层次感与细节
*   **双重阴影技巧**：通过叠加内阴影（`inset`）和外阴影来模拟真实世界的立体感，是让UI看起来更“贵”的秘诀。

    ```css
    .card {
      box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.6), /* 顶部高光 */
        0 4px 6px rgba(0,0,0,0.08);        /* 底部阴影 */
    }
    ```
*   **克制的色彩**：采用低饱和度的柔和配色（如莫兰迪色系），营造高级、舒适的视觉氛围。

### 总结

创建现代、美观的网页UI，核心在于**细节的积累**：

1.  **指针悬浮**是赋予界面“生命力”的起点，简单的上浮和阴影变化就能带来质的飞跃。
2.  **自定义右键菜单**是提升专业度和用户体验的强大工具，能让你的应用显得更成熟、功能更丰富。
3.  **UI设计**应遵循“情感化极简”原则，通过卡片、柔和圆角、细腻阴影和克制的色彩，构建清晰、有温度、有层次的界面。

---

以上指南为你提供了从交互细节到视觉风格的具体路径。如果有某个部分需要更深入的探讨，随时可以告诉我。