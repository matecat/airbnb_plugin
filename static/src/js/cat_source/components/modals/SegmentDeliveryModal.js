import Cookies from 'js-cookie'
import ModalsActions from '../../../../../../../../public/js/cat_source/es6/actions/ModalsActions'

class SegmentDeliveryModal extends React.Component {
  constructor(props) {
    super(props)
  }

  allowHTML(string) {
    return {__html: string}
  }

  checkCheckbox() {
    var checked = this.checkbox.checked
    if (checked) {
      Cookies.set('segment_delivery_modal_hide', '1', {
        expires: 30,
        secure: true,
      })
    } else {
      Cookies.set(
        'segment_delivery_modal_hide',
        null,
        //set expiration date before the current date to delete the cookie
        {expires: new Date(1), secure: true},
      )
    }
  }

  closeModal() {
    ModalsActions.onCloseModal()
  }

  componentDidUpdate() {}

  componentDidMount() {}

  componentWillUnmount() {}

  render() {
    return (
      <div className="message-modal">
        <div className="matecat-modal-middle">
          <div className={'ui one column grid ' + this.props.modalName}>
            <div className="column left aligned" style={{fontSize: '18px'}}>
              <p dangerouslySetInnerHTML={this.allowHTML(this.props.text)} />
            </div>
            <div className="column right aligned">
              <div
                className="ui primary button right floated"
                onClick={this.closeModal.bind(this)}
              >
                OK
              </div>
            </div>
          </div>
        </div>
        <div className="boxed">
          <label>
            {' '}
            Don't show this dialog again.
            <input
              type="checkbox"
              className="dont_show"
              ref={(checkbox) => (this.checkbox = checkbox)}
              onChange={this.checkCheckbox.bind(this)}
            />
          </label>
        </div>
      </div>
    )
  }
}

export default SegmentDeliveryModal
