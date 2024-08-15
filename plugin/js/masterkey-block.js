(function (wp) {
  var registerBlockType = wp.blocks.registerBlockType;
  var el = wp.element.createElement;
  var useBlockProps = wp.blockEditor.useBlockProps;

  var logo = el('svg', {
    version: "1.1", viewBox: "0 0 240 240", xmlns: "http://www.w3.org/2000/svg", width: 240, height: 240,
    dangerouslySetInnerHTML: {__html: '<path fill="#4286c0" d="M 11.995 0 L 228.128 0 C 234.626 0 239.894 5.37 239.894 11.994 L 239.894 227.638 C 239.894 234.391 234.525 239.865 227.9 239.865 L 11.766 239.865 C 5.268 239.865 0 234.495 0 227.871 L 0 12.226 C 0 5.474 5.37 0 11.995 0 Z" fill-rule="evenodd"></path><g fill="white"><path stroke="white" d="M 47.254 197.335 L 194.668 48.212" stroke-linecap="round" stroke-width="35"></path><path stroke="white" d="M 47.56 48.167 L 194.974 197.29" stroke-linecap="round" stroke-width="35"></path><ellipse fill="white" cx="121.005" cy="122.974" rx="50.573" ry="51.962"></ellipse></g><ellipse fill="#58595b" cx="120.935" cy="122.635" rx="18.357" ry="18.936"></ellipse>'},
  })

  function editTime() {
    var blockProps = useBlockProps();
    //var style = {props:{style:{ maxHeight: '200px', marginLeft: 'auto', marginRight: 'auto', display: 'block' }}};
    return el('div', blockProps, logo);//Object.assign({}, logo, style));
  }

  registerBlockType('masterkey/qrcode-block', {
    title: 'MasterKey',
    icon: logo,
    category: 'theme',
    edit: function (props) {
      props.set
      return editTime();
      // return el(ServerSideRender, {
      //     block: 'masterkey/qrcode-block',
      //     attributes: props.attributes,
      //   });
    },
    save: function(props) {
      // return el(ServerSideRender, {
      //   block: 'masterkey/qrcode-block',
      //   attributes: props.attributes,
      // });
      return null; // We're using a dynamic block, so we don't need to save anything
    },
  });
})(window.wp);