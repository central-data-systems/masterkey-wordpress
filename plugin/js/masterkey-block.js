(function (wp) {
  const {useSelect} = wp.data;
  const {registerBlockType} = wp.blocks;
  const {useBlockProps, InspectorControls} = wp.blockEditor;
  const {PanelBody, SelectControl} = wp.components;
  const ServerSideRender = wp.serverSideRender;
  const { __ } = wp.i18n;
  const el = wp.element.createElement;

  const logo = el('svg', {
    version: "1.1", viewBox: "0 0 240 240", xmlns: "http://www.w3.org/2000/svg",
    dangerouslySetInnerHTML: {__html: '<path fill="#4286c0" d="M 11.995 0 L 228.128 0 C 234.626 0 239.894 5.37 239.894 11.994 L 239.894 227.638 C 239.894 234.391 234.525 239.865 227.9 239.865 L 11.766 239.865 C 5.268 239.865 0 234.495 0 227.871 L 0 12.226 C 0 5.474 5.37 0 11.995 0 Z" fill-rule="evenodd"></path><g fill="white"><path stroke="white" d="M 47.254 197.335 L 194.668 48.212" stroke-linecap="round" stroke-width="35"></path><path stroke="white" d="M 47.56 48.167 L 194.974 197.29" stroke-linecap="round" stroke-width="35"></path><ellipse fill="white" cx="121.005" cy="122.974" rx="50.573" ry="51.962"></ellipse></g><ellipse fill="#58595b" cx="120.935" cy="122.635" rx="18.357" ry="18.936"></ellipse>'},
  })

  const ordiv = () => el('div', {id: 'masterkey-seperator'}, el('span', null, 'or'));

  function editTime(use_form) {
    return el('div', {id: 'masterkey-wrapper'},
      use_form === 'above' ? ordiv() : null,
      el('div', {id: 'masterkey-scan'},
        el('h1', null, 'Scan to Login'),
        el('div', {id: 'masterkey-scan-img'},
          logo,
          el('div', {id: 'masterkey-secured-by'},
            el('img', {src: masterkeyVars.secured_by})
          )
        )
      ),
      use_form === 'below' ? ordiv() : null,
    );
  }

  registerBlockType('masterkey/login-block', {
    title: 'MasterKey',
    icon: logo,
    category: 'theme',
    attributes: {
      use_form: {
        type: 'string',
        default: 'none',
      },
    },
    edit: function (props) {
      const { use_form } = props.attributes;
      const blockProps = useBlockProps();

      const isEditing = useSelect(function (select) {
        const editor = select('core/editor');
        return !!editor && typeof editor.getCurrentPostId === 'function';
      }, []);

      return el('div', blockProps,
        //isEditing ? editTime(use_form) :
        el(ServerSideRender, {
          block: 'masterkey/login-block',
          attributes: props.attributes,
          EmptyResponsePlaceholder: () => __('<Placeholder>'),
          ErrorResponsePlaceholder: ({ error }) => __('Error: ') + error.message,
        }),
        el(InspectorControls, null,
          el(PanelBody, {title: 'MasterKey Settings'},
            el(SelectControl, {
              label: 'Login Form Placement', value: use_form,
              options: [
                {label: 'None', value: 'none'},
                {label: 'Above', value: 'above'},
                {label: 'Below', value: 'below'},
                {label: 'Left', value: 'left'},
                {label: 'Right', value: 'right'},
              ],
              onChange: function (value) {
                props.setAttributes({use_form: value});
              },
            }),
          ),
        ),
      );
    },
    save: function(props) {
      return null; // We're using a dynamic block, so we don't need to save anything
    },
  });
})(window.wp);